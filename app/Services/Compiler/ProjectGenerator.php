<?php

namespace App\Services\Compiler;

use App\Services\Compiler\Pipeline\Steps\ValidateBlueprint;
use App\Services\Compiler\Pipeline\Steps\SortDependencies;
use App\Services\Compiler\Pipeline\Steps\GenerateWorkspaces;
use Illuminate\Pipeline\Pipeline;

class ProjectGenerator
{
    public function generate(array $blueprint): array
    {
        $payload = [
            'blueprint' => $blueprint,
        ];

        $result = app(Pipeline::class)
            ->send($payload)
            ->through([
                ValidateBlueprint::class,
                SortDependencies::class,
                GenerateWorkspaces::class,
            ])
            ->thenReturn();

        return [
            'success' => true,
            'uuid' => $result['workspace']['uuid'],
            'project' => $blueprint['project'],
            'entities_count' => count($result['sorted_entities']),
            'workspace_path' => $result['workspace']['path'],
        ];
    }
}
