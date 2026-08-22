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

    public function createFile(Request $request, string $uuid): JsonResponse
    {
        $workspacePath = storage_path("app/workspaces/{$uuid}");
        if (!is_dir($workspacePath)) {
            return response()->json(['error' => 'Workspace not found'], 404);
        }

        $request->validate(['path' => 'required|string']);
        $relativePath = $request->input('path');
        $filePath = $workspacePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        $realWorkspace = realpath($workspacePath);
        $realFile = realpath(dirname($filePath));
        if (!$realFile || !str_starts_with($realFile, $realWorkspace)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        if (file_exists($filePath)) {
            return response()->json(['error' => 'File already exists'], 409);
        }

        $dir = dirname($filePath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        file_put_contents($filePath, '');

        return response()->json(['success' => true, 'path' => $relativePath]);
    }

    public function createFolder(Request $request, string $uuid): JsonResponse
    {
        $workspacePath = storage_path("app/workspaces/{$uuid}");
        if (!is_dir($workspacePath)) {
            return response()->json(['error' => 'Workspace not found'], 404);
        }

        $request->validate(['path' => 'required|string']);
        $relativePath = $request->input('path');
        $folderPath = $workspacePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        $realWorkspace = realpath($workspacePath);
        $realFolder = realpath(dirname($folderPath));
        if (!$realFolder || !str_starts_with($realFolder, $realWorkspace)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        if (is_dir($folderPath)) {
            return response()->json(['error' => 'Folder already exists'], 409);
        }

        mkdir($folderPath, 0755, true);

        return response()->json(['success' => true, 'path' => $relativePath]);
    }

    public function deleteFile(Request $request, string $uuid): JsonResponse
    {
        $workspacePath = storage_path("app/workspaces/{$uuid}");
        if (!is_dir($workspacePath)) {
            return response()->json(['error' => 'Workspace not found'], 404);
        }

        $request->validate(['path' => 'required|string']);
        $relativePath = $request->input('path');
        $filePath = $workspacePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        $realWorkspace = realpath($workspacePath);
        $realFile = realpath($filePath);
        if (!$realFile || !str_starts_with($realFile, $realWorkspace)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        if (!file_exists($filePath)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        if (is_dir($filePath)) {
            $this->deleteDirectoryRecursive($filePath);
        } else {
            unlink($filePath);
        }

        return response()->json(['success' => true]);
    }

    public function upload(Request $request, string $uuid): JsonResponse
    {
        $workspacePath = storage_path("app/workspaces/{$uuid}");
        if (!is_dir($workspacePath)) {
            return response()->json(['error' => 'Workspace not found'], 404);
        }

        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file',
            'path' => 'nullable|string',
        ]);

        $basePath = $request->input('path', '');
        $uploaded = [];

        foreach ($request->file('files') as $file) {
            $relativePath = $basePath ? $basePath . '/' . $file->getClientOriginalName() : $file->getClientOriginalName();
            $destPath = $workspacePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

            $realWorkspace = realpath($workspacePath);
            $realDest = realpath(dirname($destPath));
            if (!$realDest || !str_starts_with($realDest, $realWorkspace)) {
                continue;
            }

            $file->move(dirname($destPath), basename($destPath));
            $uploaded[] = $relativePath;
        }

        return response()->json(['success' => true, 'uploaded' => $uploaded]);
    }

    public function terminal(Request $request, string $uuid): JsonResponse
    {
        $workspacePath = storage_path("app/workspaces/{$uuid}");
        if (!is_dir($workspacePath)) {
            return response()->json(['error' => 'Workspace not found'], 404);
        }

        $request->validate([
            'command' => 'required|string|max:2000',
        ]);

        $command = $request->input('command');

        $forbidden = ['rm -rf /', 'mkfs', 'dd if=', ':(){', 'fork'];
        foreach ($forbidden as $pattern) {
            if (str_contains($command, $pattern)) {
                return response()->json(['error' => 'Command not allowed'], 403);
            }
        }

        $cmd = "cd " . escapeshellarg($workspacePath) . " && " . $command . " 2>&1";
        $output = shell_exec($cmd);

        return response()->json([
            'output' => $output ?: '',
            'success' => true,
        ]);
    }

    protected function deleteDirectoryRecursive(string $path): void
    {
        $items = array_diff(scandir($path), ['.', '..']);
        foreach ($items as $item) {
            $full = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($full) ? $this->deleteDirectoryRecursive($full) : unlink($full);
        }
        rmdir($path);
    }
}
