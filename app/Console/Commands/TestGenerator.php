<?php

namespace App\Console\Commands;

use App\Services\Compiler\ProjectGenerator;
use Illuminate\Console\Command;

class TestGenerator extends Command
{
    protected $signature = 'test:generator';
    protected $description = 'Test the project generator pipeline';

    public function handle(): int
    {
        $blueprint = [
            'project' => 'BlogApp',
            'entities' => [
                [
                    'name' => 'User',
                    'fields' => [
                        ['name' => 'name', 'type' => 'string'],
                        ['name' => 'email', 'type' => 'string', 'unique' => true],
                        ['name' => 'password', 'type' => 'string'],
                        ['name' => 'is_admin', 'type' => 'boolean', 'default' => 'false'],
                        ['name' => 'email_verified_at', 'type' => 'datetime', 'nullable' => true],
                        ['name' => 'timestamps', 'type' => 'timestamps'],
                        ['name' => 'soft_deletes', 'type' => 'softDeletes'],
                    ],
                    'relations' => [
                        ['type' => 'hasMany', 'target' => 'Post'],
                        ['type' => 'hasMany', 'target' => 'Comment'],
                    ],
                ],
                [
                    'name' => 'Post',
                    'fields' => [
                        ['name' => 'title', 'type' => 'string'],
                        ['name' => 'slug', 'type' => 'string', 'unique' => true],
                        ['name' => 'body', 'type' => 'text'],
                        ['name' => 'excerpt', 'type' => 'text', 'nullable' => true],
                        ['name' => 'published', 'type' => 'boolean', 'default' => 'false'],
                        ['name' => 'view_count', 'type' => 'integer', 'default' => '0'],
                        ['name' => 'timestamps', 'type' => 'timestamps'],
                    ],
                    'relations' => [
                        ['type' => 'belongsTo', 'target' => 'User'],
                        ['type' => 'hasMany', 'target' => 'Comment'],
                        ['type' => 'belongsToMany', 'target' => 'Tag'],
                    ],
                ],
                [
                    'name' => 'Comment',
                    'fields' => [
                        ['name' => 'body', 'type' => 'text'],
                        ['name' => 'timestamps', 'type' => 'timestamps'],
                    ],
                    'relations' => [
                        ['type' => 'belongsTo', 'target' => 'User'],
                        ['type' => 'belongsTo', 'target' => 'Post'],
                    ],
                ],
                [
                    'name' => 'Tag',
                    'fields' => [
                        ['name' => 'name', 'type' => 'string', 'unique' => true],
                        ['name' => 'slug', 'type' => 'string', 'unique' => true],
                        ['name' => 'timestamps', 'type' => 'timestamps'],
                    ],
                    'relations' => [
                        ['type' => 'belongsToMany', 'target' => 'Post'],
                    ],
                ],
            ],
        ];

        try {
            $generator = app(ProjectGenerator::class);
            $result = $generator->generate($blueprint);

            $this->newLine();
            $this->info('Project generated successfully!');
            $this->newLine();
            $this->table(
                ['Key', 'Value'],
                [
                    ['UUID', $result['uuid']],
                    ['Project', $result['project']],
                    ['Entities', $result['entities_count']],
                    ['Path', $result['workspace_path']],
                ]
            );

            $this->newLine();
            $this->info('Generated files:');

            $files = \Illuminate\Support\Facades\File::allFiles($result['workspace_path']);
            $relativePaths = array_map(fn($f) => str_replace($result['workspace_path'] . DIRECTORY_SEPARATOR, '', $f->getPathname()), $files);
            sort($relativePaths);

            foreach ($relativePaths as $file) {
                $this->line("  <dim>{$file}</dim>");
            }

            $this->newLine();

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
