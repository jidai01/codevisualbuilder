<?php

if ($argc < 4) {
    fwrite(STDERR, "Usage: php serve-helper.php <workspace_path> <port> <log_file>\n");
    exit(1);
}

$workspacePath = $argv[1];
$port = $argv[2];
$logFile = $argv[3];
$php = PHP_BINARY;
$cmd = escapeshellarg($php) . ' artisan serve --port=' . $port . ' --host=127.0.0.1';

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['file', $logFile, 'w'],
    2 => ['file', $logFile, 'a'],
];

$process = proc_open($cmd, $descriptors, $pipes, $workspacePath);

if (is_resource($process)) {
    fclose($pipes[0]);
}
