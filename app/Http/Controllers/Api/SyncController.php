<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Compiler\AstUpdaterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(
        protected AstUpdaterService $updater
    ) {}

    public function sync(Request $request, string $uuid): JsonResponse
    {
        $workspacePath = storage_path("app/workspaces/{$uuid}");

        if (!is_dir($workspacePath)) {
            return response()->json([
                'success' => false,
                'error' => 'Workspace not found',
            ], 404);
        }

        $request->validate([
            'project' => 'required|string|max:255',
            'entities' => 'required|array|min:1',
        ]);

        try {
            $blueprint = $request->only(['project', 'entities']);

            $changes = $this->updater->sync($blueprint, $workspacePath);

            file_put_contents(
                $workspacePath . '/blueprint.json',
                json_encode($blueprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            return response()->json([
                'success' => true,
                'created' => $changes['created'],
                'updated' => $changes['updated'],
                'message' => sprintf(
                    'Created %d new, updated %d existing entities',
                    count($changes['created']),
                    count($changes['updated'])
                ),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
