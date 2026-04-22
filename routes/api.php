<?php

use App\Http\Controllers\Api\V1\EpicController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\FeatureController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TaskHistoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('tokens', [TokenController::class, 'index']);
    Route::post('tokens', [TokenController::class, 'store']);
    Route::delete('tokens/{apiToken}', [TokenController::class, 'destroy']);

    Route::apiResource('epics', EpicController::class);

    Route::get('epics/{epic}/features', [FeatureController::class, 'index']);
    Route::post('features', [FeatureController::class, 'store']);
    Route::put('features/{feature}', [FeatureController::class, 'update']);
    Route::delete('features/{feature}', [FeatureController::class, 'destroy']);

    Route::get('features/{feature}/tasks', [TaskController::class, 'index']);
    Route::post('tasks', [TaskController::class, 'store']);
    Route::get('tasks/{task}', [TaskController::class, 'show']);
    Route::put('tasks/{task}', [TaskController::class, 'update']);
    Route::delete('tasks/{task}', [TaskController::class, 'destroy']);

    Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus']);
    Route::patch('tasks/{task}/assign', [TaskController::class, 'updateAssign']);
    Route::patch('tasks/{task}/priority', [TaskController::class, 'updatePriority']);
    Route::patch('tasks/{task}/order', [TaskController::class, 'updateOrder']);

    Route::get('tasks/{task}/history', [TaskHistoryController::class, 'index']);
});
