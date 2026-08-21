<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkspaceController extends Controller
{
    public function tree(string $uuid): JsonResponse
    {
        $workspacePath = storage_path("app/workspaces/{$uuid}");

        if (!is_dir($workspacePath)) {
            return response()->json(['error' => 'Workspace not found'], 404);
        }

        $tree = $this->buildTree($workspacePath, $workspacePath);

        return response()->json($tree);
    }

    public function read(Request $request, string $uuid): JsonResponse
    {
        $workspacePath = storage_path("app/workspaces/{$uuid}");
        $relativePath = $request->query('path');

        if (!$relativePath) {
            return response()->json(['error' => 'Path parameter is required'], 400);
        }

        $filePath = $workspacePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        $realWorkspace = realpath($workspacePath);
        $realFile = realpath($filePath);

        if (!$realFile || !str_starts_with($realFile, $realWorkspace)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        if (!is_file($realFile)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $content = file_get_contents($realFile);
        $mime = mime_content_type($realFile);
        $isBinary = $this->isBinaryFile($content, $mime);

        return response()->json([
            'path' => $relativePath,
            'content' => $isBinary ? null : $content,
            'binary' => $isBinary,
            'size' => filesize($realFile),
            'mime' => $mime,
            'modified' => filemtime($realFile),
        ]);
    }

    public function write(Request $request, string $uuid): JsonResponse
    {
        $workspacePath = storage_path("app/workspaces/{$uuid}");

        if (!is_dir($workspacePath)) {
            return response()->json(['error' => 'Workspace not found'], 404);
        }

        $request->validate([
            'path' => 'required|string',
            'content' => 'required|string',
        ]);

        $relativePath = $request->input('path');
        $content = $request->input('content');

        $filePath = $workspacePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        $realWorkspace = realpath($workspacePath);
        $realFile = realpath(dirname($filePath));

        if (!$realFile || !str_starts_with($realFile, $realWorkspace)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $bytes = file_put_contents($filePath, $content);

        if ($bytes === false) {
            return response()->json(['error' => 'Failed to write file'], 500);
        }

        return response()->json([
            'success' => true,
            'path' => $relativePath,
            'bytes' => $bytes,
        ]);
    }

    protected function buildTree(string $directory, string $workspaceRoot): array
    {
        $items = [];
        $entries = array_diff(scandir($directory), ['.', '..', '.git']);

        foreach ($entries as $entry) {
            $fullPath = $directory . DIRECTORY_SEPARATOR . $entry;
            $relativePath = str_replace($workspaceRoot . DIRECTORY_SEPARATOR, '', $fullPath);
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

            if (is_dir($fullPath)) {
                $items[] = [
                    'name' => $entry,
                    'path' => $relativePath,
                    'type' => 'directory',
                    'children' => $this->buildTree($fullPath, $workspaceRoot),
                ];
            } else {
                $items[] = [
                    'name' => $entry,
                    'path' => $relativePath,
                    'type' => 'file',
                    'size' => filesize($fullPath),
                    'modified' => filemtime($fullPath),
                ];
            }
        }

        usort($items, function ($a, $b) {
            if ($a['type'] === $b['type']) {
                return strcmp($a['name'], $b['name']);
            }
            return $a['type'] === 'directory' ? -1 : 1;
        });

        return $items;
    }

    protected function isBinaryFile(string $content, ?string $mime): bool
    {
        if ($mime && !str_starts_with($mime, 'text/') && $mime !== 'application/json' && $mime !== 'application/javascript') {
            return true;
        }

        for ($i = 0; $i < min(strlen($content), 8000); $i++) {
            if (ord($content[$i]) === 0) {
                return true;
            }
        }

        return false;
    }
}
