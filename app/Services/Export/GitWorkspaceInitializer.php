<?php

namespace App\Services\Export;

use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\File;

class GitWorkspaceInitializer
{
    protected string $workspacePath;
    protected string $userName;
    protected string $userEmail;
    protected string $commitMessage;

    public function __construct(
        string $workspacePath,
        string $userName = 'Builder Bot',
        string $userEmail = 'bot@local.builder',
        string $commitMessage = 'Initial commit: Laravel Filament structure generated via Visual Builder'
    ) {
        $this->workspacePath = $workspacePath;
        $this->userName = $userName;
        $this->userEmail = $userEmail;
        $this->commitMessage = $commitMessage;
    }

    public function initialize(): array
    {
        if (!is_dir($this->workspacePath)) {
            throw new \RuntimeException('Workspace directory not found: ' . $this->workspacePath);
        }

        $results = [];

        $results[] = $this->runCommand('git init');

        $results[] = $this->runCommand('git config user.name "' . $this->userName . '"');
        $results[] = $this->runCommand('git config user.email "' . $this->userEmail . '"');

        $results[] = $this->runCommand('git add .');

        $results[] = $this->runCommand('git commit -m "' . $this->commitMessage . '"');

        foreach ($results as $result) {
            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? $result['output'],
                    'steps' => $results,
                ];
            }
        }

        return [
            'success' => true,
            'message' => 'Git repository initialized successfully',
            'steps' => $results,
        ];
    }

    protected function runCommand(string $command): array
    {
        try {
            $escapedCommand = $command;
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open(
                $escapedCommand,
                $descriptors,
                $pipes,
                $this->workspacePath,
                [
                    'GIT_TERMINAL_PROMPT' => '0',
                    'GIT_EDITOR' => 'true',
                    'LC_ALL' => 'en_US.UTF-8',
                    'PATH' => getenv('PATH'),
                ]
            );

            if (!is_resource($process)) {
                return [
                    'command' => $command,
                    'success' => false,
                    'output' => '',
                    'error' => 'Failed to start process',
                    'exitCode' => -1,
                ];
            }

            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            return [
                'command' => $command,
                'success' => $exitCode === 0,
                'output' => trim($output),
                'error' => $exitCode === 0 ? null : trim($error),
                'exitCode' => $exitCode,
            ];
        } catch (\Throwable $e) {
            return [
                'command' => $command,
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
                'exitCode' => -1,
            ];
        }
    }

    public function isInitialized(): bool
    {
        return is_dir($this->workspacePath . '/.git');
    }

    public function getStatus(): array
    {
        if (!$this->isInitialized()) {
            return ['initialized' => false];
        }

        $result = $this->runCommand('git log --oneline -5');

        $commits = [];
        if ($result['success'] && !empty($result['output'])) {
            $lines = explode("\n", $result['output']);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    $parts = explode(' ', $line, 2);
                    $commits[] = [
                        'hash' => $parts[0] ?? '',
                        'message' => $parts[1] ?? '',
                    ];
                }
            }
        }

        return [
            'initialized' => true,
            'commits' => $commits,
        ];
    }
}
