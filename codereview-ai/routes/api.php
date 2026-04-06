<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\CodeReviewController;
use App\Http\Controllers\Api\ImprovementController;

Route::post('/auth/token', [AuthController::class, 'token']);

Route::middleware('auth:sanctum')->group(function () {
    // Projects
    Route::apiResource('projects', ProjectController::class);

    // Code Reviews
    Route::post('/projects/{project}/reviews', [CodeReviewController::class, 'store']);
    Route::get('/reviews/{codeReview}', [CodeReviewController::class, 'show']);

    // Improvements
    Route::get('/projects/{project}/improvements', [ImprovementController::class, 'index']);
    Route::patch('/improvements/{improvement}', [ImprovementController::class, 'update']);
});
