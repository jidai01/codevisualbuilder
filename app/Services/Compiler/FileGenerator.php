<?php

namespace App\Services\Compiler;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class FileGenerator
{
    protected Filesystem $filesystem;
    protected StubCompiler $stubCompiler;

    public function __construct(Filesystem $filesystem, StubCompiler $stubCompiler)
    {
        $this->filesystem = $filesystem;
        $this->stubCompiler = $stubCompiler;
    }

    public function generate(array $context, string $workspacePath): void
    {
        $this->generateBaseFiles($context, $workspacePath);

        $timestamp = time();

        foreach ($context['entities'] as $entity) {
            $this->generateMigration($entity, $workspacePath, $timestamp++);
            $this->generateModel($entity, $context, $workspacePath);
            $this->generateFilamentResource($entity, $context, $workspacePath);
            $this->generateFilamentPages($entity, $workspacePath);
        }

        $this->generateSeeder($context, $workspacePath);
        $this->generateAdminPanelProvider($context, $workspacePath);
    }

    protected function generateBaseFiles(array $context, string $workspacePath): void
    {
        $projectName = $context['project'];

        $this->stubCompiler->compile(
            $this->getStubPath('composer-json'),
            [
                'vendor' => Str::slug($projectName),
                'name' => Str::slug($projectName),
                'description' => "A Laravel Filament application: {$projectName}",
            ],
            "{$workspacePath}/composer.json"
        );

        $this->stubCompiler->compile(
            $this->getStubPath('env-example'),
            ['name' => $projectName],
            "{$workspacePath}/.env.example"
        );

        $this->filesystem->put(
            "{$workspacePath}/.env",
            str_replace('APP_KEY=', 'APP_KEY=' . $this->generateAppKey(), $this->filesystem->get("{$workspacePath}/.env.example"))
        );

        $this->filesystem->put("{$workspacePath}/database/database.sqlite", '');
    }

    protected function generateMigration(array $entity, string $workspacePath, int $timestamp): void
    {
        $tableName = $this->getTableName($entity['name']);
        $datePrefix = date('Y_m_d_His', $timestamp);
        $fileName = "{$datePrefix}_create_{$tableName}_table.php";

        $columns = $this->buildMigrationColumns($entity['fields'], $entity['relations'] ?? []);
        $hasSoftDeletes = $this->hasSoftDeletes($entity['fields']);

        $variables = [
            'table' => $tableName,
            'columns' => $columns,
            'soft_deletes' => $hasSoftDeletes ? "\n            \$table->softDeletes();" : '',
        ];

        $this->stubCompiler->compile(
            $this->getStubPath('migration'),
            $variables,
            "{$workspacePath}/database/migrations/{$fileName}"
        );
    }

    protected function generateModel(array $entity, array $context, string $workspacePath): void
    {
        $className = $entity['name'];
        $tableName = $this->getTableName($className);
        $hasSoftDeletes = $this->hasSoftDeletes($entity['fields']);

        $uses = $this->buildModelImports($entity, $context);
        $fillable = $this->buildFillableFields($entity['fields']);
        $casts = $this->buildCasts($entity['fields']);
        $relations = $this->buildModelRelations($entity, $context);

        $variables = [
            'class' => $className,
            'table' => $tableName,
            'uses' => $uses,
            'soft_deletes_trait' => $hasSoftDeletes ? "\n    use \\Illuminate\\Database\\Eloquent\\SoftDeletes;" : '',
            'fillable' => $fillable,
            'casts' => $casts,
            'relations' => $relations,
        ];

        $this->stubCompiler->compile(
            $this->getStubPath('model'),
            $variables,
            "{$workspacePath}/app/Models/{$className}.php"
        );
    }

    protected function generateFilamentResource(array $entity, array $context, string $workspacePath): void
    {
        $className = $entity['name'];
        $tableName = $this->getTableName($className);
        $tablePascal = ucfirst($tableName);
        $label = ucfirst(Str::snake($tableName, ' '));

        $formFields = $this->buildFilamentFormFields($entity['fields']);
        $tableColumns = $this->buildFilamentTableColumns($entity['fields']);
        $tableFilters = $this->buildFilamentTableFilters($entity['fields']);
        $relationManagers = $this->buildRelationManagers($entity, $context);
        $extraUse = $this->buildFilamentImports($entity, $context);

        $variables = [
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
        ];

        $this->stubCompiler->compile(
            $this->getStubPath('filament-resource'),
            $variables,
            "{$workspacePath}/app/Filament/Resources/{$className}Resource.php"
        );
    }

    protected function generateFilamentPages(array $entity, string $workspacePath): void
    {
        $className = $entity['name'];
        $tableName = $this->getTableName($className);
        $tablePascal = ucfirst($tableName);
        $label = ucfirst(Str::snake($tableName, ' '));
        $pagesPath = "{$workspacePath}/app/Filament/Resources/{$className}Resource/Pages";

        $this->stubCompiler->compile(
            $this->getStubPath('filament-page-list'),
            ['class' => $className, 'table_pascal' => $tablePascal, 'label' => $label],
            "{$pagesPath}/List{$tablePascal}.php"
        );

        $this->stubCompiler->compile(
            $this->getStubPath('filament-page-create'),
            ['class' => $className, 'table_pascal' => $tablePascal],
            "{$pagesPath}/Create{$tablePascal}.php"
        );

        $this->stubCompiler->compile(
            $this->getStubPath('filament-page-edit'),
            ['class' => $className, 'table_pascal' => $tablePascal],
            "{$pagesPath}/Edit{$tablePascal}.php"
        );
    }

    protected function generateSeeder(array $context, string $workspacePath): void
    {
        $imports = '';
        $body = '';

        foreach ($context['entities'] as $entity) {
            $className = $entity['name'];
            $tableName = $this->getTableName($className);
            $imports .= "use App\\Models\\{$className};\n";
            $body .= "        {$className}::factory(10)->create();\n";
        }

        $this->stubCompiler->compile(
            $this->getStubPath('seeder'),
            [
                'class' => 'DatabaseSeeder',
                'imports' => rtrim($imports),
                'body' => rtrim($body),
            ],
            "{$workspacePath}/database/seeders/DatabaseSeeder.php"
        );
    }

    protected function generateAdminPanelProvider(array $context, string $workspacePath): void
    {
        $this->stubCompiler->compile(
            $this->getStubPath('admin-panel-provider'),
            ['primary_color' => 'Blue'],
            "{$workspacePath}/app/Providers/Filament/AdminPanelProvider.php"
        );
    }

    protected function buildMigrationColumns(array $fields, array $relations): string
    {
        $lines = [];

        foreach ($fields as $field) {
            if (in_array($field['type'], ['id', 'timestamps', 'softDeletes'])) {
                continue;
            }

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
            'string' => 'string',
            'text' => 'text',
            'integer' => 'integer',
            'bigInteger' => 'bigInteger',
            'bigIncrements' => 'bigIncrements',
            'boolean' => 'boolean',
            'datetime' => 'dateTime',
            'decimal' => 'decimal',
            'float' => 'float',
            'json' => 'json',
            'timestamps' => 'timestamps',
            'softDeletes' => 'softDeletes',
        ];

        $method = $methodMap[$type] ?? 'string';
        $line = "            \$table->{$method}('{$name}')";

        $modifiers = [];

        if (!empty($field['nullable'])) {
            $modifiers[] = '->nullable()';
        }
        if (array_key_exists('default', $field) && $field['default'] !== null) {
            $default = $field['default'];
            if ($default === 'false' || $default === 'true') {
                $modifiers[] = '->default(' . ($default === 'true' ? 'true' : 'false') . ')';
            } else {
                $modifiers[] = "->default('{$default}')";
            }
        }
        if (!empty($field['unique'])) {
            $modifiers[] = '->unique()';
        }
        if (!empty($field['index'])) {
            $modifiers[] = '->index()';
        }
        if (!empty($field['unsigned'])) {
            $modifiers[] = '->unsigned()';
        }

        if (!empty($modifiers)) {
            $line = '            $table->' . $method . "('{$name}')" . implode('', $modifiers) . ';';
        } else {
            $line .= ';';
        }

        return $line;
    }

    protected function buildModelImports(array $entity, array $context): string
    {
        $imports = [];
        $relationTypes = array_column($entity['relations'] ?? [], 'type');

        if (in_array('belongsTo', $relationTypes)) {
            $imports[] = 'use Illuminate\Database\Eloquent\Relations\BelongsTo;';
        }
        if (in_array('hasMany', $relationTypes)) {
            $imports[] = 'use Illuminate\Database\Eloquent\Relations\HasMany;';
        }
        if (in_array('hasOne', $relationTypes)) {
            $imports[] = 'use Illuminate\Database\Eloquent\Relations\HasOne;';
        }
        if (in_array('belongsToMany', $relationTypes)) {
            $imports[] = 'use Illuminate\Database\Eloquent\Relations\BelongsToMany;';
        }

        return !empty($imports) ? "\n" . implode("\n", $imports) : '';
    }

    protected function buildFillableFields(array $fields): string
    {
        $names = array_column(
            array_filter($fields, fn($f) => !in_array($f['type'], ['id', 'timestamps', 'softDeletes'])),
            'name'
        );

        if (empty($names)) {
            return '';
        }

        $lines = array_map(fn($n) => "        '{$n}',", $names);

        return "\n" . implode("\n", $lines) . "\n    ";
    }

    protected function buildCasts(array $fields): string
    {
        $casts = [];

        foreach ($fields as $field) {
            if ($field['type'] === 'json') {
                $casts[] = "        '{$field['name']}' => 'array',";
            }
            if ($field['type'] === 'boolean') {
                $casts[] = "        '{$field['name']}' => 'boolean',";
            }
            if ($field['type'] === 'datetime') {
                $casts[] = "        '{$field['name']}' => 'datetime',";
            }
            if ($field['type'] === 'decimal') {
                $casts[] = "        '{$field['name']}' => 'decimal:2',";
            }
        }

        if (empty($casts)) {
            return '';
        }

        return "\n    protected \$casts = [\n" . implode("\n", $casts) . "\n    ];";
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
                    $ownerKey = 'id';
                    $lines[] = "    public function {$method}(): BelongsTo\n    {\n        return \$this->belongsTo({$target}::class, '{$foreignKey}', '{$ownerKey}');\n    }";
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
            if (in_array($field['type'], ['id', 'timestamps'])) {
                continue;
            }

            $component = $this->getFilamentComponent($field['type']);
            $line = "                    {$component}::make('{$field['name']}')";

            if (!empty($field['nullable'])) {
                $line .= '->nullable()';
            }
            if (array_key_exists('default', $field) && $field['default'] !== null) {
                $line .= "->default('{$field['default']}')";
            }
            if (!empty($field['unique'])) {
                $line .= '->unique()';
            }

            $line .= ',';
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    protected function buildFilamentTableColumns(array $fields): string
    {
        $lines = [];

        foreach ($fields as $field) {
            if (in_array($field['type'], ['id', 'timestamps'])) {
                continue;
            }

            $column = $field['type'] === 'boolean' ? 'IconColumn' : 'TextColumn';
            $line = "                    {$column}::make('{$field['name']}')";

            if ($field['type'] === 'boolean') {
                $line .= '->boolean()';
            }
            if (!empty($field['unique'])) {
                $line .= '->sortable()';
            }

            $line .= ',';
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    protected function buildFilamentTableFilters(array $fields): string
    {
        $lines = [];

        foreach ($fields as $field) {
            if (in_array($field['type'], ['id', 'timestamps'])) {
                continue;
            }

            if ($field['type'] === 'boolean') {
                $lines[] = "                    Tables\Filters\TernaryFilter::make('{$field['name']}'),";
            } elseif (in_array($field['type'], ['string', 'text'])) {
                $lines[] = "                    Tables\Filters\Filter::make('{$field['name']}')\n                        ->searchable(),";
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
        $hasBoolean = false;

        foreach ($entity['fields'] as $field) {
            if ($field['type'] === 'boolean') {
                $hasBoolean = true;
                break;
            }
        }

        if ($hasBoolean) {
            $imports[] = 'use Filament\Tables\Filters;';
        }

        return !empty($imports) ? "\n" . implode("\n", $imports) : '';
    }

    protected function hasSoftDeletes(array $fields): bool
    {
        return in_array('softDeletes', array_column($fields, 'type'));
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

    protected function getTableName(string $className): string
    {
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));

        return Str::plural($snake);
    }

    protected function getStubPath(string $name): string
    {
        return base_path("stubs/{$name}.stub");
    }

    protected function generateAppKey(): string
    {
        $key = random_bytes(32);
        return 'base64:' . base64_encode($key);
    }
}
