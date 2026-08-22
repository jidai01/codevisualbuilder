<?php

use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ServeController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\WorkspaceController;
use App\Http\Controllers\Api\WorkspaceManagerController;
use Illuminate\Support\Facades\Route;

Route::post('/generate', [ProjectController::class, 'generate']);

Route::get('/workspaces', [WorkspaceManagerController::class, 'index']);
Route::get('/workspaces/{uuid}/blueprint', [WorkspaceManagerController::class, 'blueprint']);
Route::patch('/workspaces/{uuid}/rename', [WorkspaceManagerController::class, 'rename']);
Route::delete('/workspaces/{uuid}', [WorkspaceManagerController::class, 'destroy']);

Route::prefix('workspace/{uuid}')->group(function () {
    Route::get('/tree', [WorkspaceController::class, 'tree']);
    Route::get('/file', [WorkspaceController::class, 'read']);
    Route::put('/file', [WorkspaceController::class, 'write']);

    Route::post('/git/init', [ExportController::class, 'gitInit']);
    Route::get('/git/status', [ExportController::class, 'gitStatus']);
    Route::get('/download', [ExportController::class, 'download']);

    Route::post('/file/create', [WorkspaceController::class, 'createFile']);
    Route::post('/folder/create', [WorkspaceController::class, 'createFolder']);
    Route::post('/file/delete', [WorkspaceController::class, 'deleteFile']);
    Route::post('/file/upload', [WorkspaceController::class, 'upload']);
    Route::post('/terminal', [WorkspaceController::class, 'terminal']);

    Route::post('/sync', [SyncController::class, 'sync']);
    Route::post('/serve', [ServeController::class, 'serve']);
    Route::post('/serve/stop', [ServeController::class, 'stop']);
    Route::get('/logs', [LogController::class, 'logs']);
});
