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

        $port = $this->findAvailablePort();
        if (!$port) {
            return response()->json(['error' => 'No available ports (8001-8050)'], 500);
        }

        $this->installDependencies($workspacePath);
        $this->runMigrations($workspacePath);
        $this->startProcess($uuid, $port, $workspacePath);
        $this->trackServer($uuid, $port);

        sleep(1);

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

    protected function installDependencies(string $workspacePath): void
    {
        if (!is_dir("{$workspacePath}/vendor")) {
            $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
            $cmd = "{$php} artisan --version 2>&1";
            $output = shell_exec("cd " . escapeshellarg($workspacePath) . " && {$php} -v 2>&1");
        }
    }

    protected function runMigrations(string $workspacePath): void
    {
        $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
        shell_exec("cd " . escapeshellarg($workspacePath) . " && {$php} artisan migrate --force 2>&1");
    }

    protected function startProcess(string $uuid, int $port, string $workspacePath): void
    {
        $logDir = storage_path("app/workspaces/{$uuid}/storage/logs");
        File::ensureDirectoryExists($logDir, 0755, true);

        $logFile = $logDir . '/artisan-serve.log';
        $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
        $artisan = $workspacePath . DIRECTORY_SEPARATOR . 'artisan';

        if (!file_exists($artisan)) {
            return;
        }

        if (str_starts_with(PHP_OS, 'WIN')) {
            $cmd = $php . ' ' . escapeshellarg($artisan) . ' serve --port=' . $port . ' --host=127.0.0.1';
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['file', $logFile, 'w'],
                2 => ['file', $logFile, 'a'],
            ];
            $process = proc_open($cmd, $descriptors, $pipes, $workspacePath);
            if (is_resource($process)) {
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
            }
        } else {
            $cmd = "cd " . escapeshellarg($workspacePath) . " && nohup {$php} artisan serve --port={$port} --host=127.0.0.1 > " . escapeshellarg($logFile) . " 2>&1 &";
            exec($cmd);
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
