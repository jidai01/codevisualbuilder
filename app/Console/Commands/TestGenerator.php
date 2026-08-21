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
                    ],
                    'relations' => [
                        ['type' => 'hasMany', 'target' => 'Post'],
                    ],
                ],
                [
                    'name' => 'Post',
                    'fields' => [
                        ['name' => 'title', 'type' => 'string'],
                        ['name' => 'body', 'type' => 'text'],
                        ['name' => 'published', 'type' => 'boolean', 'default' => 'false'],
                    ],
                    'relations' => [
                        ['type' => 'belongsTo', 'target' => 'User'],
                    ],
                ],
            ],
        ];

        try {
            $generator = app(ProjectGenerator::class);
            $result = $generator->generate($blueprint);

            $this->info('Project generated successfully!');
            $this->table(
                ['Key', 'Value'],
                [
                    ['UUID', $result['uuid']],
                    ['Project', $result['project']],
                    ['Entities', $result['entities_count']],
                    ['Path', $result['workspace_path']],
                ]
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
