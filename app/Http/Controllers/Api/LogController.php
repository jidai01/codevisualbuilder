<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class LogController extends Controller
{
    public function logs(string $uuid): JsonResponse
    {
        $workspacePath = storage_path("app/workspaces/{$uuid}");

        if (!is_dir($workspacePath)) {
            return response()->json(['lines' => [], 'error' => 'Workspace not found'], 404);
        }

        $logFile = "{$workspacePath}/storage/logs/laravel.log";
        $artisanLog = "{$workspacePath}/storage/logs/artisan-serve.log";

        $lines = [];

        if (File::exists($artisanLog)) {
            $lines = array_merge($lines, $this->tailFile($artisanLog, 30));
        }

        if (File::exists($logFile)) {
            $lines = array_merge($lines, $this->tailFile($logFile, 50));
        }

        return response()->json(['lines' => $lines]);
    }

    protected function tailFile(string $path, int $lines): array
    {
        $content = file_get_contents($path);
        if ($content === false) return [];

        $allLines = explode("\n", $content);
        $allLines = array_filter($allLines, fn($line) => trim($line) !== '');
        $allLines = array_values($allLines);

        return array_slice($allLines, -$lines);
    }
}
