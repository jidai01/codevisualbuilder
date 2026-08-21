<?php

namespace App\Services\Compiler;

use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;

class WorkspaceGenerator
{
    protected Filesystem $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function create(string $projectName): array
    {
        $uuid = Str::uuid()->toString();
        $workspacePath = storage_path("app/workspaces/{$uuid}");

        $directories = [
            $workspacePath,
            "{$workspacePath}/app/Models",
            "{$workspacePath}/app/Filament/Resources",
            "{$workspacePath}/app/Filament/Resources/Pages",
            "{$workspacePath}/database/migrations",
            "{$workspacePath}/database/seeders",
            "{$workspacePath}/config",
            "{$workspacePath}/routes",
        ];

        foreach ($directories as $directory) {
            $this->filesystem->makeDirectory($directory, 0755, true, true);
        }

        $this->filesystem->put(
            "{$workspacePath}/.env",
            $this->generateEnvContent($projectName)
        );

        $this->filesystem->copy(
            base_path('artisan'),
            "{$workspacePath}/artisan"
        );

        return [
            'uuid' => $uuid,
            'path' => $workspacePath,
        ];
    }

    protected function generateEnvContent(string $projectName): string
    {
        return <<<ENV
        APP_NAME='{$projectName}'
        APP_ENV=local
        APP_KEY=
        APP_DEBUG=true
        APP_URL=http://localhost:8000

        APP_LOCALE=en
        APP_FALLBACK_LOCALE=en
        APP_FAKER_LOCALE=en_US

        APP_MAINTENANCE_DRIVER=file

        BCRYPT_ROUNDS=12

        LOG_CHANNEL=stack
        LOG_STACK=single
        LOG_DEPRECATIONS_CHANNEL=null
        LOG_LEVEL=debug

        DB_CONNECTION=sqlite

        SESSION_DRIVER=database
        SESSION_LIFETIME=120
        SESSION_ENCRYPT=false
        SESSION_PATH=/
        SESSION_DOMAIN=null

        BROADCAST_CONNECTION=log
        FILESYSTEM_DISK=local
        QUEUE_CONNECTION=database

        CACHE_STORE=database

        MAIL_MAILER=log
        MAIL_HOST=127.0.0.1
        MAIL_PORT=2525
        MAIL_USERNAME=null
        MAIL_PASSWORD=null
        MAIL_ENCRYPTION=null
        MAIL_FROM_ADDRESS="hello@example.com"
        MAIL_FROM_NAME="\${APP_NAME}"
        ENV;
    }
}
