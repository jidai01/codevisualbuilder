<?php

namespace App\Services\Compiler\Pipeline\Steps;

use App\Services\Compiler\WorkspaceGenerator;
use App\Services\Compiler\FileGenerator;
use Closure;
use Illuminate\Support\Facades\Log;

class GenerateWorkspaces
{
    public function __construct(
        protected WorkspaceGenerator $workspaceGenerator,
        protected FileGenerator $fileGenerator,
    ) {}

    public function handle(array $payload, Closure $next): array
    {
        $workspace = $this->workspaceGenerator->create($payload['blueprint']['project']);

        $context = [
            'project' => $payload['blueprint']['project'],
            'entities' => $payload['sorted_entities'],
        ];

        $this->fileGenerator->generate($context, $workspace['path']);

        file_put_contents("{$workspace['path']}/database/database.sqlite", '');

        $this->installDependencies($workspace['path']);
        $this->runMigrations($workspace['path']);

        $payload['workspace'] = $workspace;

        return $next($payload);
    }

    protected function installDependencies(string $workspacePath): void
    {
        $cmd = "cd " . escapeshellarg($workspacePath) . " && composer install --no-dev --no-interaction --no-scripts 2>&1";

        Log::info("Running composer install in: {$workspacePath}");

        $previousTimeout = ini_get('max_execution_time');
        set_time_limit(300);

        $output = shell_exec($cmd);

        set_time_limit((int) $previousTimeout);

        Log::info("Composer output: " . substr($output ?? '', -1000));

        if (!is_dir("{$workspacePath}/vendor")) {
            Log::error("vendor/ directory not created. Output: " . ($output ?? 'empty'));
        }
    }

    protected function runMigrations(string $workspacePath): void
    {
        $php = PHP_BINARY;

        $cmd = "cd " . escapeshellarg($workspacePath) . " && {$php} artisan key:generate --force 2>&1";
        $output = shell_exec($cmd);
        Log::info("Key generate: " . substr($output ?? '', -500));

        $cmd = "cd " . escapeshellarg($workspacePath) . " && {$php} artisan migrate --force 2>&1";
        $output = shell_exec($cmd);
        Log::info("Migration output: " . substr($output ?? '', -500));
    }
}
