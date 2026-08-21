<?php

namespace App\Services\Compiler\Pipeline\Steps;

use App\Services\Compiler\WorkspaceGenerator;
use App\Services\Compiler\FileGenerator;
use Closure;

class GenerateWorkspaces
{
    protected WorkspaceGenerator $workspaceGenerator;
    protected FileGenerator $fileGenerator;

    public function __construct(WorkspaceGenerator $workspaceGenerator, FileGenerator $fileGenerator)
    {
        $this->workspaceGenerator = $workspaceGenerator;
        $this->fileGenerator = $fileGenerator;
    }

    public function handle(array $payload, Closure $next): array
    {
        $workspace = $this->workspaceGenerator->create($payload['blueprint']['project']);

        $context = [
            'project' => $payload['blueprint']['project'],
            'entities' => $payload['sorted_entities'],
        ];

        $this->fileGenerator->generate($context, $workspace['path']);

        $payload['workspace'] = $workspace;

        return $next($payload);
    }
}
