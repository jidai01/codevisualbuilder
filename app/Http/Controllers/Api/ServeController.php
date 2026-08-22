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

        $existingPort = $this->getRunningPort($uuid);
        if ($existingPort && $this->isPortInUse((int) $existingPort)) {
            return response()->json([
                'url' => "http://127.0.0.1:{$existingPort}",
                'port' => (int) $existingPort,
            ]);
        }

        if (!file_exists("{$workspacePath}/artisan")) {
            return response()->json(['error' => 'Invalid workspace: artisan file missing. Regenerate the project.'], 400);
        }

        $port = $this->findAvailablePort();
        if (!$port) {
            return response()->json(['error' => 'No available ports (8001-8050)'], 500);
        }

        $installError = $this->installDependencies($workspacePath);
        if ($installError) {
            return response()->json(['error' => "Composer install failed: {$installError}"], 500);
        }

        $migrateError = $this->runMigrations($workspacePath);
        if ($migrateError) {
            return response()->json(['error' => "Migration failed: {$migrateError}"], 500);
        }

        $startError = $this->startProcess($uuid, $port, $workspacePath);
        if ($startError) {
            return response()->json(['error' => $startError], 500);
        }

        $this->trackServer($uuid, $port);

        sleep(2);

        if (!$this->isPortInUse($port)) {
            $this->untrackServer($uuid);
            $logFile = storage_path("app/workspaces/{$uuid}/storage/logs/artisan-serve.log");
            $logContent = File::exists($logFile) ? File::get($logFile) : '';
            $shortLog = substr(trim($logContent), -500);
            return response()->json(['error' => "Server did not start. Log: {$shortLog}"], 500);
        }

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
                preg_match_all('/\s(\d+)\s*$/', $output, $matches);
                $pids = array_unique(array_filter($matches[1] ?? []));
                foreach ($pids as $pid) {
                    exec("taskkill /PID {$pid} /F 2>nul");
                }
            }
        } else {
            exec("lsof -ti:{$port} | xargs kill -9 2>/dev/null");
        }
    }

    protected function installDependencies(string $workspacePath): ?string
    {
        if (is_dir("{$workspacePath}/vendor")) {
            return null;
        }

        $php = PHP_BINARY;
        $cmd = "cd " . escapeshellarg($workspacePath) . " && {$php} ../composer.phar install --no-dev --no-interaction 2>&1";

        if (!file_exists(storage_path('app/composer.phar'))) {
            $cmd = "cd " . escapeshellarg($workspacePath) . " && composer install --no-dev --no-interaction 2>&1";
        }

        $output = shell_exec($cmd);

        if (!is_dir("{$workspacePath}/vendor")) {
            return $output ?: 'vendor directory not created';
        }

        return null;
    }

    protected function runMigrations(string $workspacePath): ?string
    {
        $php = PHP_BINARY;
        $cmd = "cd " . escapeshellarg($workspacePath) . " && {$php} artisan migrate --force 2>&1";
        $output = shell_exec($cmd);

        if (str_contains($output ?? '', 'Error') || str_contains($output ?? '', 'Exception')) {
            return $output;
        }

        return null;
    }

    protected function startProcess(string $uuid, int $port, string $workspacePath): ?string
    {
        $logDir = storage_path("app/workspaces/{$uuid}/storage/logs");
        File::ensureDirectoryExists($logDir, 0755, true);

        $logFile = $logDir . '/artisan-serve.log';
        File::put($logFile, '');

        $php = PHP_BINARY;
        $artisan = $workspacePath . DIRECTORY_SEPARATOR . 'artisan';

        if (str_starts_with(PHP_OS, 'WIN')) {
            $cmd = "start /B cmd /c \"cd /d " . escapeshellarg($workspacePath) . " && " . escapeshellarg($php) . " artisan serve --port={$port} --host=127.0.0.1 > " . escapeshellarg($logFile) . " 2>&1\"";
            exec($cmd, $output, $exitCode);
        } else {
            $cmd = "cd " . escapeshellarg($workspacePath) . " && nohup {$php} artisan serve --port={$port} --host=127.0.0.1 > " . escapeshellarg($logFile) . " 2>&1 &";
            exec($cmd, $output, $exitCode);
        }

        return null;
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
