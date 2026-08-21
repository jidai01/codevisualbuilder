<?php

use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::post('/generate', [ProjectController::class, 'generate']);

Route::prefix('workspace/{uuid}')->group(function () {
    Route::get('/tree', [WorkspaceController::class, 'tree']);
    Route::get('/file', [WorkspaceController::class, 'read']);
    Route::put('/file', [WorkspaceController::class, 'write']);

    Route::post('/git/init', [ExportController::class, 'gitInit']);
    Route::get('/git/status', [ExportController::class, 'gitStatus']);
    Route::get('/download', [ExportController::class, 'download']);
});
