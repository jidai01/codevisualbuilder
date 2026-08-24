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
            "{$workspacePath}/bootstrap",
            "{$workspacePath}/bootstrap/cache",
            "{$workspacePath}/config",
            "{$workspacePath}/database/migrations",
            "{$workspacePath}/database/seeders",
            "{$workspacePath}/public",
            "{$workspacePath}/resources/views",
            "{$workspacePath}/routes",
            "{$workspacePath}/storage/app",
            "{$workspacePath}/storage/app/public",
            "{$workspacePath}/storage/framework/cache",
            "{$workspacePath}/storage/framework/sessions",
            "{$workspacePath}/storage/framework/views",
            "{$workspacePath}/storage/logs",
        ];

        foreach ($directories as $directory) {
            $this->filesystem->makeDirectory($directory, 0755, true, true);
        }

        $this->filesystem->put("{$workspacePath}/.env", $this->generateEnvContent($projectName));
        $this->filesystem->copy(base_path('artisan'), "{$workspacePath}/artisan");
        $this->filesystem->put("{$workspacePath}/composer.json", $this->generateComposerJson($projectName));

        $this->filesystem->put("{$workspacePath}/bootstrap/app.php", $this->generateBootstrapApp());
        $this->filesystem->put("{$workspacePath}/config/app.php", $this->generateConfigApp($projectName));
        $this->filesystem->put("{$workspacePath}/config/database.php", $this->generateConfigDatabase());
        $this->filesystem->put("{$workspacePath}/config/logging.php", $this->generateConfigLogging());
        $this->filesystem->put("{$workspacePath}/config/services.php", $this->generateConfigServices());
        $this->filesystem->put("{$workspacePath}/routes/web.php", $this->generateRoutesWeb());
        $this->filesystem->put("{$workspacePath}/routes/api.php", $this->generateRoutesApi());
        $this->filesystem->put("{$workspacePath}/routes/console.php", $this->generateRoutesConsole());
        $this->filesystem->put("{$workspacePath}/public/index.php", $this->generatePublicIndex());

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
        APP_URL=http://localhost:8001

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

    protected function generateBootstrapApp(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
PHP;
    }

    protected function generateConfigApp(string $projectName): string
    {
        return <<<PHP
        <?php

        return [
            'name' => env('APP_NAME', '{$projectName}'),
            'env' => env('APP_ENV', 'production'),
            'debug' => (bool) env('APP_DEBUG', false),
            'url' => env('APP_URL', 'http://localhost'),
            'timezone' => 'UTC',
            'locale' => env('APP_LOCALE', 'en'),
            'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
            'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),
            'cipher' => 'AES-256-CBC',
            'key' => env('APP_KEY'),
            'previous_keys' => [
                ...array_filter(explode(',', (string) env('APP_PREVIOUS_KEYS', ''))),
            ],
            'maintenance' => [
                'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
                'store' => env('APP_MAINTENANCE_STORE', 'database'),
            ],
        ];
        PHP;
    }

    protected function generateConfigDatabase(): string
    {
        return <<<'PHP'
<?php

return [
    'default' => env('DB_CONNECTION', 'sqlite'),
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],
    ],
    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],
];
PHP;
    }

    protected function generateConfigLogging(): string
    {
        return <<<'PHP'
<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],
        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => Monolog\Handler\StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],
        'null' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\NullHandler::class,
        ],
        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
PHP;
    }

    protected function generateConfigServices(): string
    {
        return <<<'PHP'
<?php

return [
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'resend' => [
        'key' => env('RESEND_KEY'),
    ],
    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
];
PHP;
    }

    protected function generateComposerJson(string $projectName): string
    {
        $slug = Str::slug($projectName);
        return <<<JSON
        {
            "name": "app/{$slug}",
            "type": "project",
            "description": "{$projectName}",
            "license": "MIT",
            "require": {
                "php": "^8.2",
                "filament/filament": "^3.0",
                "laravel/framework": "^13.0",
                "laravel/tinker": "^3.0"
            },
            "require-dev": {
                "fakerphp/faker": "^1.23",
                "laravel/pint": "^1.27",
                "mockery/mockery": "^1.6",
                "nunomaduro/collision": "^8.6",
                "phpunit/phpunit": "^12.5.12"
            },
            "autoload": {
                "psr-4": {
                    "App\\\\": "app/",
                    "Database\\\\Factories\\\\": "database/factories/",
                    "Database\\\\Seeders\\\\": "database/seeders/"
                }
            },
            "autoload-dev": {
                "psr-4": {
                    "Tests\\\\": "tests/"
                }
            },
            "scripts": {
                "post-autoload-dump": [
                    "Illuminate\\\\Foundation\\\\ComposerScripts::postAutoloadDump",
                    "@php artisan package:discover --ansi"
                ]
            },
            "extra": {
                "laravel": {
                    "dont-discover": []
                }
            },
            "config": {
                "optimize-autoloader": true,
                "preferred-install": "dist",
                "sort-packages": true,
                "allow-plugins": {
                    "pestphp/pest-plugin": true,
                    "php-http/discovery": true
                },
                "policy": {
                    "advisories": {
                        "block": false
                    }
                }
            },
            "minimum-stability": "stable",
            "prefer-stable": true
        }
        JSON;
    }

    protected function generateRoutesWeb(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'Laravel API is running']);
});
PHP;
    }

    protected function generateRoutesApi(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;
PHP;
    }

    protected function generateRoutesConsole(): string
    {
        return <<<'PHP'
<?php
PHP;
    }

    protected function generatePublicIndex(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
PHP;
    }
}
