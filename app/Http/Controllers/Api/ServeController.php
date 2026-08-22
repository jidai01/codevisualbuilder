<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class ServeController extends Controller
{
    protected static string $trackerFile = 'app/workspaces/.serve-tracker.json';

    public function serve(string $uuid): JsonResponse
    {
        $workspacePath = storage_path("app/workspaces/{$uuid}");

        if (!is_dir($workspacePath)) {
            return response()->json(['error' => 'Workspace not found'], 404);
        }

        $existingPort = $this->getRunningPort($uuid);
        if ($existingPort) {
            return response()->json([
                'url' => "http://localhost:{$existingPort}",
                'port' => (int) $existingPort,
            ]);
        }

        $port = $this->findAvailablePort();
        if (!$port) {
            return response()->json(['error' => 'No available ports (8001-8050)'], 500);
        }

        $this->installDependencies($workspacePath);
        $this->runMigrations($workspacePath);

        $this->startProcess($uuid, $port, $workspacePath);
        $this->trackServer($uuid, $port);

        return response()->json([
            'url' => "http://localhost:{$port}",
            'port' => $port,
        ]);
    }

    public function stop(string $uuid): JsonResponse
    {
        $port = $this->getRunningPort($uuid);
        if ($port) {
            $this->killPort($port);
            $this->untrackServer($uuid);
        }
        return response()->json(['success' => true]);
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
        if (str_starts_with(PHP_OS, 'WIN')) {
            $output = shell_exec("netstat -ano | findstr :{$port} | findstr LISTENING");
            return !empty($output);
        }
        $output = shell_exec("lsof -i:{$port} 2>/dev/null");
        return !empty($output);
    }

    protected function killPort(int $port): void
    {
        if (str_starts_with(PHP_OS, 'WIN')) {
            $output = shell_exec("netstat -ano | findstr :{$port} | findstr LISTENING");
            if ($output) {
                preg_match_all('/\s(\d+)$/', $output, $matches);
                $pids = array_unique($matches[1] ?? []);
                foreach ($pids as $pid) {
                    exec("taskkill /PID {$pid} /F 2>nul");
                }
            }
        } else {
            exec("lsof -ti:{$port} | xargs kill -9 2>/dev/null");
        }
    }

    protected function installDependencies(string $workspacePath): void
    {
        if (!is_dir("{$workspacePath}/vendor")) {
            Process::run("composer install --no-dev --no-interaction --working-dir={$workspacePath}");
        }
    }

    protected function runMigrations(string $workspacePath): void
    {
        Process::run("php artisan migrate --force --working-dir={$workspacePath}");
    }

    protected function startProcess(string $uuid, int $port, string $workspacePath): void
    {
        $logFile = storage_path("app/workspaces/{$uuid}/storage/logs/artisan-serve.log");
        File::ensureDirectoryExists(dirname($logFile));

        if (str_starts_with(PHP_OS, 'WIN')) {
            $cmd = "cmd /c \"cd /d \"{$workspacePath}\" && php artisan serve --port={$port} --host=127.0.0.1 > \"{$logFile}\" 2>&1\"";
            $wsh = new \COM("WScript.Shell");
            $wsh->Run($cmd, 0, false);
        } else {
            exec("cd {$workspacePath} && nohup php artisan serve --port={$port} --host=127.0.0.1 > {$logFile} 2>&1 &");
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
