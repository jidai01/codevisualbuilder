<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Export\GitWorkspaceInitializer;
use App\Services\Export\WorkspaceZipper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function download(string $uuid): void
    {
        $workspacePath = storage_path("app/workspaces/{$uuid}");

        if (!is_dir($workspacePath)) {
            response()->json(['error' => 'Workspace not found'], 404)->send();
            exit;
        }

        try {
            $zipper = new WorkspaceZipper($workspacePath);
            $zipper->stream();
        } catch (\Throwable $e) {
            response()->json(['error' => 'Failed to create ZIP: ' . $e->getMessage()], 500)->send();
            exit;
        }
    }

    public function gitInit(Request $request, string $uuid): JsonResponse
    {
        $workspacePath = storage_path("app/workspaces/{$uuid}");

        if (!is_dir($workspacePath)) {
            return response()->json(['error' => 'Workspace not found'], 404);
        }

        $request->validate([
            'user_name' => 'sometimes|string|max:100',
            'user_email' => 'sometimes|email|max:255',
            'commit_message' => 'sometimes|string|max:500',
        ]);

        $initializer = new GitWorkspaceInitializer(
            $workspacePath,
            $request->input('user_name', 'Builder Bot'),
            $request->input('user_email', 'bot@local.builder'),
            $request->input('commit_message', 'Initial commit: Laravel Filament structure generated via Visual Builder')
        );

        if ($initializer->isInitialized()) {
            $status = $initializer->getStatus();
            return response()->json([
                'success' => true,
                'message' => 'Git repository already initialized',
                'already_initialized' => true,
                'status' => $status,
            ]);
        }

        $result = $initializer->initialize();

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Git repository initialized with initial commit',
                'already_initialized' => false,
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'] ?? 'Failed to initialize Git repository',
            'steps' => $result['steps'],
        ], 500);
    }

    public function gitStatus(string $uuid): JsonResponse
    {
        $workspacePath = storage_path("app/workspaces/{$uuid}");

        if (!is_dir($workspacePath)) {
            return response()->json(['error' => 'Workspace not found'], 404);
        }

        $initializer = new GitWorkspaceInitializer($workspacePath);
        $status = $initializer->getStatus();

        return response()->json($status);
    }
}
