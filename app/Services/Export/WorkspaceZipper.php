<?php

namespace App\Services\Export;

use ZipArchive;
use Illuminate\Support\Facades\File;

class WorkspaceZipper
{
    protected string $workspacePath;
    protected string $zipPath;

    public function __construct(string $workspacePath)
    {
        $this->workspacePath = $workspacePath;
        $this->zipPath = storage_path('app/exports/' . basename($workspacePath) . '.zip');
    }

    public function zip(): string
    {
        File::ensureDirectoryExists(dirname($this->zipPath));

        if (File::exists($this->zipPath)) {
            File::delete($this->zipPath);
        }

        $zip = new ZipArchive();

        if ($zip->open($this->zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to create ZIP archive');
        }

        $this->addDirectoryToZip($zip, $this->workspacePath, '');

        $zip->close();

        if (!File::exists($this->zipPath)) {
            throw new \RuntimeException('ZIP file was not created');
        }

        return $this->zipPath;
    }

    public function stream(): void
    {
        $zipPath = $this->zip();

        $fileName = basename($this->workspacePath) . '.zip';
        $fileSize = File::size($zipPath);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');

        $handle = fopen($zipPath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Failed to read ZIP file');
        }

        $chunkSize = 8192;
        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            if ($chunk === false) {
                break;
            }
            echo $chunk;
            flush();
        }

        fclose($handle);

        File::delete($zipPath);

        exit;
    }

    protected function addDirectoryToZip(ZipArchive $zip, string $directory, string $zipPath): void
    {
        $files = File::allFiles($directory);

        foreach ($files as $file) {
            $relativePath = str_replace($directory . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

            $zip->addFile($file->getPathname(), $zipPath . $relativePath);
        }
    }

    public function getZipPath(): string
    {
        return $this->zipPath;
    }

    public function getFileName(): string
    {
        return basename($this->workspacePath) . '.zip';
    }
}
