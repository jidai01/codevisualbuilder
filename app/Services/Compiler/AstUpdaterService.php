<?php

namespace App\Services\Compiler;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class AstUpdaterService
{
    protected Filesystem $filesystem;
    protected StubCompiler $stubCompiler;

    public function __construct(Filesystem $filesystem, StubCompiler $stubCompiler)
    {
        $this->filesystem = $filesystem;
        $this->stubCompiler = $stubCompiler;
    }

    public function sync(array $blueprint, string $workspacePath): array
    {
        $changes = ['created' => [], 'updated' => []];

        foreach ($blueprint['entities'] as $entity) {
            $className = $entity['name'];
            $tableName = $this->getTableName($className);

            $modelPath = "{$workspacePath}/app/Models/{$className}.php";
            $resourcePath = "{$workspacePath}/app/Filament/Resources/{$className}Resource.php";
            $seederPath = "{$workspacePath}/database/seeders/DatabaseSeeder.php";

            if ($this->filesystem->exists($modelPath)) {
                $this->updateModel($entity, $blueprint, $modelPath);
                $this->updateFilamentResource($entity, $blueprint, $resourcePath);
                $changes['updated'][] = $className;
            } else {
                $this->createNewEntity($entity, $blueprint, $workspacePath);
                $changes['created'][] = $className;
            }
        }

        $this->updateSeeder($blueprint, $workspacePath);
        $this->updateAdminPanelProvider($blueprint, $workspacePath);

        return $changes;
    }

    protected function createNewEntity(array $entity, array $context, string $workspacePath): void
    {
        $className = $entity['name'];
        $tableName = $this->getTableName($className);

        $timestamp = time();
        $datePrefix = date('Y_m_d_His', $timestamp);
        $migrationFile = "{$workspacePath}/database/migrations/{$datePrefix}_create_{$tableName}_table.php";

        $columns = $this->buildMigrationColumns($entity['fields'], $entity['relations'] ?? []);
        $hasSoftDeletes = $this->hasSoftDeletes($entity['fields']);

        $this->stubCompiler->compile(
            base_path('stubs/migration.stub'),
            [
                'table' => $tableName,
                'columns' => $columns,
                'soft_deletes' => $hasSoftDeletes ? "\n            \$table->softDeletes();" : '',
            ],
            $migrationFile
        );

        $uses = $this->buildModelImports($entity, $context);
        $fillable = $this->buildFillableFields($entity['fields']);
        $casts = $this->buildCasts($entity['fields']);
        $relations = $this->buildModelRelations($entity, $context);

        $this->stubCompiler->compile(
            base_path('stubs/model.stub'),
            [
                'class' => $className,
                'table' => $tableName,
                'uses' => $uses,
                'soft_deletes_trait' => $hasSoftDeletes ? "\n    use \\Illuminate\\Database\\Eloquent\\SoftDeletes;" : '',
                'fillable' => $fillable,
                'casts' => $casts,
                'relations' => $relations,
            ],
            "{$workspacePath}/app/Models/{$className}.php"
        );

        $formFields = $this->buildFilamentFormFields($entity['fields']);
        $tableColumns = $this->buildFilamentTableColumns($entity['fields']);
        $tableFilters = $this->buildFilamentTableFilters($entity['fields']);
        $relationManagers = $this->buildRelationManagers($entity, $context);
        $extraUse = $this->buildFilamentImports($entity, $context);
        $tablePascal = ucfirst($tableName);
        $label = ucfirst(Str::snake($tableName, ' '));

        $this->stubCompiler->compile(
            base_path('stubs/filament-resource.stub'),
            [
                'class' => $className,
                'table_pascal' => $tablePascal,
                'label' => $label,
                'icon' => 'heroicon-o-rectangle-stack',
                'group' => ucfirst(Str::snake($context['project'], ' ')),
                'supports' => '',
                'form_fields' => $formFields,
                'table_columns' => $tableColumns,
                'table_filters' => $tableFilters,
                'relation_managers' => $relationManagers,
                'extra_use' => $extraUse,
            ],
            "{$workspacePath}/app/Filament/Resources/{$className}Resource.php"
        );

        $pagesPath = "{$workspacePath}/app/Filament/Resources/{$className}Resource/Pages";
        $this->filesystem->makeDirectory($pagesPath, 0755, true, true);

        foreach (['filament-page-list', 'filament-page-create', 'filament-page-edit'] as $stubName) {
            $pageFile = match($stubName) {
                'filament-page-list' => "List{$tablePascal}.php",
                'filament-page-create' => "Create{$tablePascal}.php",
                'filament-page-edit' => "Edit{$tablePascal}.php",
            };
            $this->stubCompiler->compile(
                base_path("stubs/{$stubName}.stub"),
                ['class' => $className, 'table_pascal' => $tablePascal, 'label' => $label],
                "{$pagesPath}/{$pageFile}"
            );
        }
    }

    protected function updateModel(array $entity, array $context, string $modelPath): void
    {
        $content = $this->filesystem->get($modelPath);
        $className = $entity['name'];

        $newFillable = $this->extractNewFillableFields($entity['fields']);
        if (!empty($newFillable)) {
            $content = $this->injectIntoFillable($content, $newFillable);
        }

        $newRelations = $this->buildNewModelRelations($entity, $context, $content);
        if (!empty($newRelations)) {
            $content = $this->injectBeforeClosingBrace($content, $newRelations);
        }

        $this->filesystem->put($modelPath, $content);
    }

    protected function extractNewFillableFields(array $fields): array
    {
        return array_filter(
            array_column($fields, 'name'),
            fn($name) => !in_array($name, ['id', 'timestamps', 'softDeletes'])
        );
    }

    protected function injectIntoFillable(string $content, array $newFields): string
    {
        $pattern = '/protected\s+\$fillable\s*=\s*\[(.*?)\]/s';
        if (preg_match($pattern, $content, $matches)) {
            $existing = $matches[1];
            $additions = '';
            foreach ($newFields as $field) {
                $trimmed = trim($existing);
                if (strpos($trimmed, "'{$field}'") !== false || strpos($trimmed, "\"{$field}\"") !== false) {
                    continue;
                }
                $additions .= "\n        '{$field}',";
            }
            if (!empty($additions)) {
                $newContent = rtrim($existing) . $additions . "\n    ";
                $content = str_replace($matches[1], $newContent, $content);
            }
        }
        return $content;
    }

    protected function buildNewModelRelations(array $entity, array $context, string $existingContent): string
    {
        $lines = [];
        foreach ($entity['relations'] ?? [] as $relation) {
            $target = $relation['target'];
            $type = $relation['type'];
            $method = Str::camel($type . ucfirst($target));

            if (strpos($existingContent, "function {$method}") !== false) {
                continue;
            }

            switch ($type) {
                case 'belongsTo':
                    $foreignKey = $relation['foreignKey'] ?? strtolower($target) . '_id';
                    $lines[] = "\n    public function {$method}(): BelongsTo\n    {\n        return \$this->belongsTo({$target}::class, '{$foreignKey}');\n    }";
                    break;
                case 'hasMany':
                    $lines[] = "\n    public function {$method}(): HasMany\n    {\n        return \$this->hasMany({$target}::class);\n    }";
                    break;
                case 'hasOne':
                    $lines[] = "\n    public function {$method}(): HasOne\n    {\n        return \$this->hasOne({$target}::class);\n    }";
                    break;
                case 'belongsToMany':
                    $pivotTable = $relation['pivotTable'] ?? $this->getTableName($entity['name']) . '_' . $this->getTableName($target);
                    $lines[] = "\n    public function {$method}(): BelongsToMany\n    {\n        return \$this->belongsToMany({$target}::class, '{$pivotTable}');\n    }";
                    break;
            }
        }
        return implode("\n", $lines);
    }

    protected function injectBeforeClosingBrace(string $content, string $injection): string
    {
        $lastBrace = strrpos($content, '}');
        if ($lastBrace !== false) {
            $content = substr_replace($content, $injection . "\n", $lastBrace, 0);
        }
        return $content;
    }

    protected function updateFilamentResource(array $entity, array $context, string $resourcePath): void
    {
        if (!$this->filesystem->exists($resourcePath)) return;

        $content = $this->filesystem->get($resourcePath);

        $newFormFields = $this->buildFilamentFormFields($entity['fields']);
        if (!empty($newFormFields)) {
            $content = $this->injectIntoFormSchema($content, $newFormFields);
        }

        $newTableColumns = $this->buildFilamentTableColumns($entity['fields']);
        if (!empty($newTableColumns)) {
            $content = $this->injectIntoTableColumns($content, $newTableColumns);
        }

        $this->filesystem->put($resourcePath, $content);
    }

    protected function injectIntoFormSchema(string $content, string $newFields): string
    {
        $pattern = '/->schema\(\[(.*?)\]\)/s';
        if (preg_match($pattern, $content, $matches)) {
            $existing = $matches[1];
            $trimmedExisting = rtrim($existing);
            $additions = "\n" . $newFields;
            $newContent = $trimmedExisting . $additions . "\n            ";
            $content = str_replace($matches[1], $newContent, $content);
        }
        return $content;
    }

    protected function injectIntoTableColumns(string $content, string $newColumns): string
    {
        $pattern = '/->columns\(\[(.*?)\]\)/s';
        if (preg_match($pattern, $content, $matches)) {
            $existing = $matches[1];
            $trimmedExisting = rtrim($existing);
            $additions = "\n" . $newColumns;
            $newContent = $trimmedExisting . $additions . "\n            ";
            $content = str_replace($matches[1], $newContent, $content);
        }
        return $content;
    }

    protected function updateSeeder(array $context, string $workspacePath): void
    {
        $seederPath = "{$workspacePath}/database/seeders/DatabaseSeeder.php";
        if (!$this->filesystem->exists($seederPath)) return;

        $content = $this->filesystem->get($seederPath);

        foreach ($context['entities'] as $entity) {
            $className = $entity['name'];
            $importLine = "use App\\Models\\{$className};";
            $seedLine = "        {$className}::factory(10)->create();";

            if (strpos($content, $importLine) === false) {
                $content = str_replace(
                    'use Illuminate\Database\Seeder;',
                    "use Illuminate\Database\Seeder;\n{$importLine}",
                    $content
                );
            }

            if (strpos($content, $seedLine) === false) {
                $content = str_replace(
                    "    }\n}",
                    "        {$seedLine}\n    }\n}",
                    $content
                );
            }
        }

        $this->filesystem->put($seederPath, $content);
    }

    protected function updateAdminPanelProvider(array $context, string $workspacePath): void
    {
        $providerPath = "{$workspacePath}/app/Providers/Filament/AdminPanelProvider.php";
        if ($this->filesystem->exists($providerPath)) return;

        $this->stubCompiler->compile(
            base_path('stubs/admin-panel-provider.stub'),
            ['primary_color' => 'Blue'],
            $providerPath
        );
    }

    // --- Reuse FileGenerator helpers ---

    protected function buildMigrationColumns(array $fields, array $relations): string
    {
        $lines = [];
        foreach ($fields as $field) {
            if (in_array($field['type'], ['id', 'timestamps', 'softDeletes'])) continue;
            $lines[] = $this->buildColumnLine($field);
        }
        foreach ($relations as $relation) {
            if ($relation['type'] === 'belongsTo') {
                $foreignKey = $relation['foreignKey'] ?? strtolower($relation['target']) . '_id';
                $lines[] = "            \$table->foreignId('{$foreignKey}')->constrained()->cascadeOnDelete();";
            }
        }
        return "\n" . implode("\n", $lines);
    }

    protected function buildColumnLine(array $field): string
    {
        $type = $field['type'];
        $name = $field['name'];
        $methodMap = [
            'string' => 'string', 'text' => 'text', 'integer' => 'integer',
            'bigInteger' => 'bigInteger', 'bigIncrements' => 'bigIncrements',
            'boolean' => 'boolean', 'datetime' => 'dateTime', 'decimal' => 'decimal',
            'float' => 'float', 'json' => 'json', 'timestamps' => 'timestamps',
            'softDeletes' => 'softDeletes',
        ];
        $method = $methodMap[$type] ?? 'string';
        $modifiers = [];
        if (!empty($field['nullable'])) $modifiers[] = '->nullable()';
        if (array_key_exists('default', $field) && $field['default'] !== null) {
            $default = $field['default'];
            $modifiers[] = ($default === 'true' || $default === 'false')
                ? "->default(" . ($default === 'true' ? 'true' : 'false') . ")"
                : "->default('{$default}')";
        }
        if (!empty($field['unique'])) $modifiers[] = '->unique()';
        if (!empty($field['index'])) $modifiers[] = '->index()';
        if (!empty($field['unsigned'])) $modifiers[] = '->unsigned()';

        return empty($modifiers)
            ? "            \$table->{$method}('{$name}');"
            : "            \$table->{$method}('{$name}')" . implode('', $modifiers) . ';';
    }

    protected function buildModelImports(array $entity, array $context): string
    {
        $imports = [];
        $relationTypes = array_column($entity['relations'] ?? [], 'type');
        if (in_array('belongsTo', $relationTypes)) $imports[] = 'use Illuminate\Database\Eloquent\Relations\BelongsTo;';
        if (in_array('hasMany', $relationTypes)) $imports[] = 'use Illuminate\Database\Eloquent\Relations\HasMany;';
        if (in_array('hasOne', $relationTypes)) $imports[] = 'use Illuminate\Database\Eloquent\Relations\HasOne;';
        if (in_array('belongsToMany', $relationTypes)) $imports[] = 'use Illuminate\Database\Eloquent\Relations\BelongsToMany;';
        return !empty($imports) ? "\n" . implode("\n", $imports) : '';
    }

    protected function buildFillableFields(array $fields): string
    {
        $names = array_column(array_filter($fields, fn($f) => !in_array($f['type'], ['id', 'timestamps', 'softDeletes'])), 'name');
        if (empty($names)) return '';
        $lines = array_map(fn($n) => "        '{$n}',", $names);
        return "\n" . implode("\n", $lines) . "\n    ";
    }

    protected function buildCasts(array $fields): string
    {
        $casts = [];
        foreach ($fields as $field) {
            if ($field['type'] === 'json') $casts[] = "        '{$field['name']}' => 'array',";
            if ($field['type'] === 'boolean') $casts[] = "        '{$field['name']}' => 'boolean',";
            if ($field['type'] === 'datetime') $casts[] = "        '{$field['name']}' => 'datetime',";
            if ($field['type'] === 'decimal') $casts[] = "        '{$field['name']}' => 'decimal:2',";
        }
        return !empty($casts) ? "\n    protected \$casts = [\n" . implode("\n", $casts) . "\n    ];" : '';
    }

    protected function buildModelRelations(array $entity, array $context): string
    {
        $lines = [];
        foreach ($entity['relations'] ?? [] as $relation) {
            $target = $relation['target'];
            $type = $relation['type'];
            $method = Str::camel($type . ucfirst($target));
            switch ($type) {
                case 'belongsTo':
                    $foreignKey = $relation['foreignKey'] ?? strtolower($target) . '_id';
                    $lines[] = "    public function {$method}(): BelongsTo\n    {\n        return \$this->belongsTo({$target}::class, '{$foreignKey}');\n    }";
                    break;
                case 'hasMany':
                    $lines[] = "    public function {$method}(): HasMany\n    {\n        return \$this->hasMany({$target}::class);\n    }";
                    break;
                case 'hasOne':
                    $lines[] = "    public function {$method}(): HasOne\n    {\n        return \$this->hasOne({$target}::class);\n    }";
                    break;
                case 'belongsToMany':
                    $pivotTable = $relation['pivotTable'] ?? $this->getTableName($entity['name']) . '_' . $this->getTableName($target);
                    $lines[] = "    public function {$method}(): BelongsToMany\n    {\n        return \$this->belongsToMany({$target}::class, '{$pivotTable}');\n    }";
                    break;
            }
        }
        return !empty($lines) ? "\n" . implode("\n\n", $lines) . "\n" : '';
    }

    protected function buildFilamentFormFields(array $fields): string
    {
        $lines = [];
        foreach ($fields as $field) {
            if (in_array($field['type'], ['id', 'timestamps'])) continue;
            $component = $this->getFilamentComponent($field['type']);
            $line = "                    {$component}::make('{$field['name']}')";
            if (!empty($field['nullable'])) $line .= '->nullable()';
            if (array_key_exists('default', $field) && $field['default'] !== null) $line .= "->default('{$field['default']}')";
            if (!empty($field['unique'])) $line .= '->unique()';
            $line .= ',';
            $lines[] = $line;
        }
        return implode("\n", $lines);
    }

    protected function buildFilamentTableColumns(array $fields): string
    {
        $lines = [];
        foreach ($fields as $field) {
            if (in_array($field['type'], ['id', 'timestamps'])) continue;
            $column = $field['type'] === 'boolean' ? 'IconColumn' : 'TextColumn';
            $line = "                    {$column}::make('{$field['name']}')";
            if ($field['type'] === 'boolean') $line .= '->boolean()';
            if (!empty($field['unique'])) $line .= '->sortable()';
            $line .= ',';
            $lines[] = $line;
        }
        return implode("\n", $lines);
    }

    protected function buildFilamentTableFilters(array $fields): string
    {
        $lines = [];
        foreach ($fields as $field) {
            if (in_array($field['type'], ['id', 'timestamps'])) continue;
            if ($field['type'] === 'boolean') {
                $lines[] = "                    Tables\\Filters\\TernaryFilter::make('{$field['name']}'),";
            } elseif (in_array($field['type'], ['string', 'text'])) {
                $lines[] = "                    Tables\\Filters\\Filter::make('{$field['name']}')\n                        ->searchable(),";
            }
        }
        return implode("\n", $lines);
    }

    protected function buildRelationManagers(array $entity, array $context): string
    {
        $lines = [];
        foreach ($entity['relations'] ?? [] as $relation) {
            if ($relation['type'] === 'hasMany' || $relation['type'] === 'belongsToMany') {
                $target = $relation['target'];
                $method = Str::camel($relation['type'] . ucfirst($target));
                $lines[] = "            // {$method}()";
            }
        }
        return !empty($lines) ? implode("\n", $lines) : '';
    }

    protected function buildFilamentImports(array $entity, array $context): string
    {
        $imports = [];
        foreach ($entity['fields'] as $field) {
            if ($field['type'] === 'boolean') { $imports[] = 'use Filament\Tables\Filters;'; break; }
        }
        return !empty($imports) ? "\n" . implode("\n", $imports) : '';
    }

    protected function getFilamentComponent(string $type): string
    {
        return match ($type) {
            'text', 'string' => 'Forms\\TextInput',
            'integer', 'bigInteger', 'bigIncrements' => 'Forms\\TextInput',
            'boolean' => 'Forms\\Toggle',
            'datetime' => 'Forms\\DateTimePicker',
            'decimal', 'float' => 'Forms\\TextInput',
            'json' => 'Forms\\Textarea',
            default => 'Forms\\TextInput',
        };
    }

    protected function hasSoftDeletes(array $fields): bool
    {
        return in_array('softDeletes', array_column($fields, 'type'));
    }

    protected function getTableName(string $className): string
    {
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
        return Str::plural($snake);
    }
}
