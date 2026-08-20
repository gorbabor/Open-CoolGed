<?php

use App\Http\Controllers\ApiController;
use App\Http\Middleware\ApiAuth;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(ApiAuth::class)->group(function () {
    Route::get('/me', [ApiController::class, 'me']);
    Route::get('/documents', [ApiController::class, 'documents']);
    Route::get('/documents/{document}', [ApiController::class, 'showDocument']);
    Route::get('/search', [ApiController::class, 'search']);
    Route::get('/rag', [ApiController::class, 'rag']);
    Route::post('/documents/{document}/ai', [ApiController::class, 'aiJobs']);
    Route::get('/tasks', [ApiController::class, 'tasks']);
});
