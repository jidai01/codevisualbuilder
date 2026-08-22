<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
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
}
