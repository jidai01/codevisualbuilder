<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class ServeController extends Controller
{
    protected static string $trackerFile = 'app/workspaces/.serve-tracker.json';

    public function serve(string $uuid): JsonResponse
    {
        $workspacePath = storage_path("app/workspaces/{$uuid}");

        if (!is_dir($workspacePath)) {
            return response()->json(['error' => 'Workspace not found'], 404);
        }

        if (!file_exists("{$workspacePath}/artisan")) {
            return response()->json(['error' => 'Invalid workspace: artisan file missing.'], 400);
        }

        $existingPort = $this->getRunningPort($uuid);
        if ($existingPort && $this->isPortInUse((int) $existingPort)) {
            return response()->json([
                'url' => "http://127.0.0.1:{$existingPort}",
                'port' => (int) $existingPort,
            ]);
        }

        if (!is_dir("{$workspacePath}/vendor")) {
            $skeletonError = $this->ensureSkeleton($workspacePath);
            if ($skeletonError) {
                return response()->json(['error' => $skeletonError], 500);
            }
            $installError = $this->installDependencies($workspacePath);
            if ($installError) {
                return response()->json(['error' => "Composer install failed: {$installError}"], 500);
            }
            $this->runMigrations($workspacePath);
        }

        $port = $this->findAvailablePort();
        if (!$port) {
            return response()->json(['error' => 'No available ports (8001-8050)'], 500);
        }

        $this->startProcess($uuid, $port, $workspacePath);
        $this->trackServer($uuid, $port);

        return response()->json([
            'url' => "http://127.0.0.1:{$port}",
            'port' => $port,
        ]);
    }

    public function stop(string $uuid): JsonResponse
    {
        $port = $this->getRunningPort($uuid);
        if ($port) {
            $this->killPort((int) $port);
            $this->untrackServer($uuid);
        }
        return response()->json(['success' => true]);
    }

    protected function installDependencies(string $workspacePath): ?string
    {
        $cmd = "cd " . escapeshellarg($workspacePath) . " && composer install --no-dev --no-interaction --no-scripts 2>&1";

        $prev = ini_get('max_execution_time');
        set_time_limit(300);

        $output = shell_exec($cmd);

        set_time_limit((int) $prev);

        if (!is_dir("{$workspacePath}/vendor")) {
            return $output ?: 'vendor directory not created';
        }

        return null;
    }

    protected function ensureSkeleton(string $workspacePath): ?string
    {
        $dirs = [
            "{$workspacePath}/bootstrap",
            "{$workspacePath}/bootstrap/cache",
            "{$workspacePath}/config",
            "{$workspacePath}/routes",
            "{$workspacePath}/public",
            "{$workspacePath}/resources/views",
            "{$workspacePath}/storage/app",
            "{$workspacePath}/storage/app/public",
            "{$workspacePath}/storage/framework/cache",
            "{$workspacePath}/storage/framework/sessions",
            "{$workspacePath}/storage/framework/views",
            "{$workspacePath}/storage/logs",
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        $files = [
            'bootstrap/app.php' => <<<'PHP'
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
PHP,
            'routes/web.php' => <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'Laravel API is running']);
});
PHP,
            'routes/api.php' => "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n",
            'routes/console.php' => "<?php\n",
            'public/index.php' => <<<'PHP'
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
PHP,
            'config/database.php' => <<<'PHP'
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
PHP,
            'config/logging.php' => <<<'PHP'
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
        'null' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\NullHandler::class,
        ],
        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
PHP,
            'config/services.php' => <<<'PHP'
<?php

return [
    'postmark' => ['token' => env('POSTMARK_TOKEN')],
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'resend' => ['key' => env('RESEND_KEY')],
];
PHP,
        ];

        foreach ($files as $relativePath => $content) {
            $fullPath = "{$workspacePath}/{$relativePath}";
            if (!file_exists($fullPath)) {
                file_put_contents($fullPath, $content);
            }
        }

        $composerJson = "{$workspacePath}/composer.json";
        $existing = file_exists($composerJson) ? json_decode(file_get_contents($composerJson), true) : null;
        $needsFix = !$existing || !isset($existing['require']['laravel/framework'])
            || str_contains($existing['require']['laravel/framework'] ?? '', '^11');

        if ($needsFix) {
            $slug = basename($workspacePath);
            file_put_contents("{$workspacePath}/composer.json", json_encode([
                'name' => "app/{$slug}",
                'type' => 'project',
                'license' => 'MIT',
                'require' => [
                    'php' => '^8.2',
                    'filament/filament' => '^3.0',
                    'laravel/framework' => '^13.0',
                    'laravel/tinker' => '^3.0',
                ],
                'require-dev' => [
                    'fakerphp/faker' => '^1.23',
                    'laravel/pint' => '^1.27',
                    'mockery/mockery' => '^1.6',
                    'nunomaduro/collision' => '^8.6',
                    'phpunit/phpunit' => '^12.5.12',
                ],
                'autoload' => [
                    'psr-4' => [
                        'App\\\\' => 'app/',
                        'Database\\\\Factories\\\\' => 'database/factories/',
                        'Database\\\\Seeders\\\\' => 'database/seeders/',
                    ],
                ],
                'autoload-dev' => [
                    'psr-4' => ['Tests\\\\' => 'tests/'],
                ],
                'scripts' => [
                    'post-autoload-dump' => [
                        'Illuminate\\\\Foundation\\\\ComposerScripts::postAutoloadDump',
                        '@php artisan package:discover --ansi',
                    ],
                ],
                'extra' => ['laravel' => ['dont-discover' => []]],
                'config' => [
                    'optimize-autoloader' => true,
                    'preferred-install' => 'dist',
                    'sort-packages' => true,
                    'allow-plugins' => [
                        'pestphp/pest-plugin' => true,
                        'php-http/discovery' => true,
                    ],
                    'policy' => ['advisories' => ['block' => false]],
                ],
                'minimum-stability' => 'stable',
                'prefer-stable' => true,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        if (!file_exists("{$workspacePath}/database/database.sqlite")) {
            file_put_contents("{$workspacePath}/database/database.sqlite", '');
        }

        return null;
    }

    protected function runMigrations(string $workspacePath): void
    {
        $php = PHP_BINARY;

        file_put_contents("{$workspacePath}/database/database.sqlite", '');

        shell_exec("cd " . escapeshellarg($workspacePath) . " && {$php} artisan key:generate --force 2>&1");
        shell_exec("cd " . escapeshellarg($workspacePath) . " && {$php} artisan migrate --force 2>&1");
    }

    protected function findAvailablePort(): ?int
    {
        for ($port = 8001; $port <= 8050; $port++) {
            if (!$this->isPortInUse($port)) {
                return $port;
            }
        }
        return null;
    }

    protected function isPortInUse(int $port): bool
    {
        $sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
        if ($sock) {
            fclose($sock);
            return true;
        }
        return false;
    }

    protected function killPort(int $port): void
    {
        if (str_starts_with(PHP_OS, 'WIN')) {
            exec("for /f \"tokens=5\" %a in ('netstat -ano ^| findstr :{$port} ^| findstr LISTENING') do taskkill /PID %a /F 2>nul");
        } else {
            exec("lsof -ti:{$port} | xargs kill -9 2>/dev/null");
        }
    }

    protected function startProcess(string $uuid, int $port, string $workspacePath): void
    {
        $logDir = storage_path("app/workspaces/{$uuid}/storage/logs");
        File::ensureDirectoryExists($logDir, 0755, true);

        $logFile = $logDir . '/artisan-serve.log';
        File::put($logFile, '');

        $php = PHP_BINARY;
        $cmd = "{$php} artisan serve --port={$port} --host=127.0.0.1";

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $logFile, 'w'],
            2 => ['file', $logFile, 'a'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes, $workspacePath);

        if (is_resource($process)) {
            fclose($pipes[0]);
        }
    }

    protected function trackServer(string $uuid, int $port): void
    {
        $tracker = $this->loadTracker();
        $tracker[$uuid] = [
            'port' => $port,
            'started_at' => now()->toISOString(),
        ];
        File::put(storage_path(self::$trackerFile), json_encode($tracker, JSON_PRETTY_PRINT));
    }

    protected function untrackServer(string $uuid): void
    {
        $tracker = $this->loadTracker();
        unset($tracker[$uuid]);
        File::put(storage_path(self::$trackerFile), json_encode($tracker, JSON_PRETTY_PRINT));
    }

    protected function getRunningPort(string $uuid): ?string
    {
        $tracker = $this->loadTracker();
        return isset($tracker[$uuid]) ? (string) $tracker[$uuid]['port'] : null;
    }

    protected function loadTracker(): array
    {
        $path = storage_path(self::$trackerFile);
        if (!File::exists($path)) return [];
        return json_decode(File::get($path), true) ?? [];
    }
}
