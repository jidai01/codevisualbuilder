<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class WorkspaceManagerController extends Controller
{
    public function index(): JsonResponse
    {
        $workspacesPath = storage_path('app/workspaces');

        if (!File::isDirectory($workspacesPath)) {
            return response()->json([]);
        }

        $directories = File::directories($workspacesPath);
        $workspaces = [];

        foreach ($directories as $dir) {
            $uuid = basename($dir);
            $blueprintPath = $dir . '/blueprint.json';

            if (!File::exists($blueprintPath)) {
                continue;
            }

            $blueprint = json_decode(File::get($blueprintPath), true);

            $workspaces[] = [
                'uuid' => $uuid,
                'project_name' => $blueprint['project'] ?? 'Untitled',
                'entities_count' => count($blueprint['entities'] ?? []),
                'last_updated' => filemtime($blueprintPath),
            ];
        }

        usort($workspaces, fn($a, $b) => $b['last_updated'] - $a['last_updated']);

        return response()->json($workspaces);
    }

    public function blueprint(string $uuid): JsonResponse
    {
        $blueprintPath = storage_path("app/workspaces/{$uuid}/blueprint.json");

        if (!File::exists($blueprintPath)) {
            return response()->json([
                'error' => 'Workspace or blueprint not found',
            ], 404);
        }

        $blueprint = json_decode(File::get($blueprintPath), true);

        if ($blueprint === null) {
            return response()->json([
                'error' => 'Invalid blueprint JSON',
            ], 500);
        }

        return response()->json($blueprint);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $workspacePath = storage_path("app/workspaces/{$uuid}");

        if (!is_dir($workspacePath)) {
            return response()->json(['error' => 'Workspace not found'], 404);
        }

        $trackerPath = storage_path('app/workspaces/.serve-tracker.json');
        if (File::exists($trackerPath)) {
            $tracker = json_decode(File::get($trackerPath), true) ?? [];
            if (isset($tracker[$uuid])) {
                $port = $tracker[$uuid]['port'];
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
                unset($tracker[$uuid]);
                File::put($trackerPath, json_encode($tracker, JSON_PRETTY_PRINT));
            }
        }

        File::deleteDirectory($workspacePath);

        return response()->json(['success' => true]);
    }

    public function rename(Request $request, string $uuid): JsonResponse
    {
        $workspacePath = storage_path("app/workspaces/{$uuid}");

        if (!is_dir($workspacePath)) {
            return response()->json(['error' => 'Workspace not found'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $newName = trim($request->input('name'));
        $newName = preg_replace('/[^\w\s\-\.]/', '', $newName);

        if (empty($newName)) {
            return response()->json(['error' => 'Invalid project name'], 422);
        }

        $blueprintPath = "{$workspacePath}/blueprint.json";
        if (File::exists($blueprintPath)) {
            $blueprint = json_decode(File::get($blueprintPath), true);
            $blueprint['project'] = $newName;
            File::put($blueprintPath, json_encode($blueprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $envPath = "{$workspacePath}/.env";
        if (File::exists($envPath)) {
            $envContent = File::get($envPath);
            $escapedName = str_contains($newName, ' ') ? '"' . $newName . '"' : $newName;
            $envContent = preg_replace('/^APP_NAME=.*/m', "APP_NAME={$escapedName}", $envContent);
            File::put($envPath, $envContent);
        }

        return response()->json(['success' => true, 'name' => $newName]);
    }
}
