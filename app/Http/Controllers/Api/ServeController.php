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

        if (!is_dir("{$workspacePath}/vendor")) {
            return response()->json(['error' => 'Dependencies not installed. Regenerate the project.'], 400);
        }

        $existingPort = $this->getRunningPort($uuid);
        if ($existingPort && $this->isPortInUse((int) $existingPort)) {
            return response()->json([
                'url' => "http://127.0.0.1:{$existingPort}",
                'port' => (int) $existingPort,
            ]);
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
