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
        foreach ($context['entities'] as $entity) {
            $this->generateMigration($entity, $workspacePath);
            $this->generateModel($entity, $context, $workspacePath);
            $this->generateFilamentResource($entity, $context, $workspacePath);
        }
    }

    protected function generateMigration(array $entity, string $workspacePath): void
    {
        $tableName = $this->getTableName($entity['name']);
        $timestamp = date('Y_m_d_His');
        $fileName = "{$timestamp}_create_{$tableName}_table.php";
        $outputPath = "{$workspacePath}/database/migrations/{$fileName}";

        $fields = $this->compileMigrationFields($entity['fields'], $entity['relations'] ?? []);

        $variables = [
            'class_name' => 'Create' . ucfirst($tableName) . 'Table',
            'table_name' => $tableName,
            'fields' => $fields,
        ];

        $this->stubCompiler->compile(
            $this->getStubPath('migration'),
            $variables,
            $outputPath
        );
    }

    protected function generateModel(array $entity, array $context, string $workspacePath): void
    {
        $outputPath = "{$workspacePath}/app/Models/{$entity['name']}.php";
        $relations = $this->compileModelRelations($entity, $context);

        $variables = [
            'class_name' => $entity['name'],
            'table_name' => $this->getTableName($entity['name']),
            'fillable_fields' => $this->compileFillableFields($entity['fields']),
            'relations' => $relations,
        ];

        $this->stubCompiler->compile(
            $this->getStubPath('model'),
            $variables,
            $outputPath
        );
    }

    protected function generateFilamentResource(array $entity, array $context, string $workspacePath): void
    {
        $resourcePath = "{$workspacePath}/app/Filament/Resources/{$entity['name']}Resource.php";
        $pagesPath = "{$workspacePath}/app/Filament/Resources/Pages";

        $variables = [
            'class_name' => $entity['name'],
            'model_name' => $entity['name'],
            'table_name' => $this->getTableName($entity['name']),
            'form_fields' => $this->compileFormFields($entity['fields']),
            'table_columns' => $this->compileTableColumns($entity['fields']),
        ];

        $this->stubCompiler->compile(
            $this->getStubPath('filament-resource'),
            $variables,
            $resourcePath
        );

        $this->filesystem->makeDirectory("{$pagesPath}/List" . ucfirst($this->getTableName($entity['name'])), 0755, true, true);
        $this->filesystem->makeDirectory("{$pagesPath}/Create" . ucfirst($this->getTableName($entity['name'])), 0755, true, true);
        $this->filesystem->makeDirectory("{$pagesPath}/Edit" . ucfirst($this->getTableName($entity['name'])), 0755, true, true);
    }

    protected function compileMigrationFields(array $fields, array $relations): string
    {
        $lines = [];

        foreach ($fields as $field) {
            $line = "\$table->{$field['type']}('{$field['name']}')";

            if (isset($field['nullable']) && $field['nullable']) {
                $line .= '->nullable()';
            }
            if (isset($field['default'])) {
                $line .= "->default('{$field['default']}')";
            }
            if (isset($field['unique']) && $field['unique']) {
                $line .= '->unique()';
            }
            if (isset($field['index']) && $field['index']) {
                $line .= '->index()';
            }
            if (isset($field['unsigned']) && $field['unsigned']) {
                $line .= '->unsigned()';
            }

            $line .= ';';
            $lines[] = $line;
        }

        foreach ($relations as $relation) {
            if ($relation['type'] === 'belongsTo') {
                $foreignKey = $relation['foreignKey'] ?? strtolower($relation['target']) . '_id';
                $lines[] = "\$table->foreignId('{$foreignKey}')->constrained()->cascadeOnDelete();";
            }
        }

        return implode("\n            ", $lines);
    }

    protected function compileFillableFields(array $fields): string
    {
        $names = array_column(
            array_filter($fields, fn($f) => !in_array($f['type'], ['id', 'timestamps', 'softDeletes'])),
            'name'
        );

        $quoted = array_map(fn($n) => "'{$n}'", $names);

        return implode(', ', $quoted);
    }

    protected function compileModelRelations(array $entity, array $context): string
    {
        $lines = [];

        foreach ($entity['relations'] ?? [] as $relation) {
            $target = $relation['target'];
            $method = strtolower($relation['type']) . ucfirst($target);

            switch ($relation['type']) {
                case 'belongsTo':
                    $lines[] = "public function {$method}(): BelongsTo\n    {\n        return \$this->belongsTo({$target}::class);\n    }";
                    break;
                case 'hasMany':
                    $lines[] = "public function {$method}(): HasMany\n    {\n        return \$this->hasMany({$target}::class);\n    }";
                    break;
                case 'hasOne':
                    $lines[] = "public function {$method}(): HasOne\n    {\n        return \$this->hasOne({$target}::class);\n    }";
                    break;
                case 'belongsToMany':
                    $pivotTable = $relation['pivotTable'] ?? $this->getTableName($entity['name']) . '_' . $this->getTableName($target);
                    $lines[] = "public function {$method}(): BelongsToMany\n    {\n        return \$this->belongsToMany({$target}::class, '{$pivotTable}');\n    }";
                    break;
            }
        }

        return implode("\n\n    ", $lines);
    }

    protected function compileFormFields(array $fields): string
    {
        $lines = [];

        foreach ($fields as $field) {
            if (in_array($field['type'], ['id', 'timestamps'])) {
                continue;
            }

            $component = match ($field['type']) {
                'text', 'string' => 'TextInput',
                'integer', 'bigInteger', 'bigIncrements' => 'TextInput',
                'boolean' => 'Toggle',
                'datetime' => 'DateTimePicker',
                'decimal', 'float' => 'TextInput',
                'json' => 'Textarea',
                default => 'TextInput',
            };

            $lines[] = "{$component}::make('{$field['name']}'),";
        }

        return implode("\n                ", $lines);
    }

    protected function compileTableColumns(array $fields): string
    {
        $lines = [];

        foreach ($fields as $field) {
            if (in_array($field['type'], ['id', 'timestamps'])) {
                continue;
            }

            $lines[] = "TextColumn::make('{$field['name']}'),";
        }

        return implode("\n                ", $lines);
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
}
