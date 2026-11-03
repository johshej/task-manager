<?php

use App\Http\Controllers\Api\V1\AutomatedTestController;
use App\Http\Controllers\Api\V1\EpicController;
use App\Http\Controllers\Api\V1\EpicHistoryController;
use App\Http\Controllers\Api\V1\FeatureController;
use App\Http\Controllers\Api\V1\FeatureHistoryController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TaskHistoryController;
use App\Http\Controllers\Api\V1\TestCommentController;
use App\Http\Controllers\Api\V1\TestContextController;
use App\Http\Controllers\Api\V1\TestMatrixController;
use App\Http\Controllers\Api\V1\TestResultController;
use App\Http\Controllers\Api\V1\TestScenarioController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::post('sessions', [SessionController::class, 'store']);
    Route::patch('sessions/{session}/heartbeat', [SessionController::class, 'heartbeat']);
    Route::delete('sessions/{session}', [SessionController::class, 'destroy']);

    Route::get('tokens', [TokenController::class, 'index']);
    Route::post('tokens', [TokenController::class, 'store']);
    Route::delete('tokens/{apiToken}', [TokenController::class, 'destroy']);

    Route::apiResource('epics', EpicController::class);
    Route::get('epics/{epic}/features', [FeatureController::class, 'index']);
    Route::get('epics/{epic}/history', [EpicHistoryController::class, 'index']);
    Route::post('epics/{epic}/history', [EpicHistoryController::class, 'store']);

    Route::get('features/{feature}', [FeatureController::class, 'show']);
    Route::post('features', [FeatureController::class, 'store']);
    Route::put('features/{feature}', [FeatureController::class, 'update']);
    Route::delete('features/{feature}', [FeatureController::class, 'destroy']);
    Route::get('features/{feature}/history', [FeatureHistoryController::class, 'index']);
    Route::post('features/{feature}/history', [FeatureHistoryController::class, 'store']);

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
    Route::post('tasks/{task}/history', [TaskHistoryController::class, 'store']);

    // Test matrices: scenarios as rows, contexts as columns, a result in every cell.
    Route::get('epics/{epic}/test-matrices', [TestMatrixController::class, 'index']);
    Route::post('test-matrices', [TestMatrixController::class, 'store']);
    Route::get('test-matrices/{testMatrix}', [TestMatrixController::class, 'show']);
    Route::put('test-matrices/{testMatrix}', [TestMatrixController::class, 'update']);
    Route::delete('test-matrices/{testMatrix}', [TestMatrixController::class, 'destroy']);

    Route::get('test-matrices/{testMatrix}/contexts', [TestContextController::class, 'index']);
    Route::post('test-contexts', [TestContextController::class, 'store']);
    Route::get('test-contexts/{testContext}', [TestContextController::class, 'show']);
    Route::put('test-contexts/{testContext}', [TestContextController::class, 'update']);
    Route::delete('test-contexts/{testContext}', [TestContextController::class, 'destroy']);

    Route::get('test-matrices/{testMatrix}/scenarios', [TestScenarioController::class, 'index']);
    Route::post('test-scenarios', [TestScenarioController::class, 'store']);
    Route::get('test-scenarios/{testScenario}', [TestScenarioController::class, 'show']);
    Route::put('test-scenarios/{testScenario}', [TestScenarioController::class, 'update']);
    Route::put('test-scenarios/{testScenario}/relevance', [TestScenarioController::class, 'updateRelevance']);
    Route::delete('test-scenarios/{testScenario}', [TestScenarioController::class, 'destroy']);

    Route::get('test-matrices/{testMatrix}/results', [TestResultController::class, 'index']);
    Route::get('test-results/{testResult}', [TestResultController::class, 'show']);
    Route::match(['put', 'patch'], 'test-results/{testResult}', [TestResultController::class, 'update']);

    Route::get('test-matrices/{testMatrix}/comments', [TestCommentController::class, 'index']);
    Route::post('test-comments', [TestCommentController::class, 'store']);
    Route::get('test-comments/{testComment}', [TestCommentController::class, 'show']);
    Route::put('test-comments/{testComment}', [TestCommentController::class, 'update']);
    Route::delete('test-comments/{testComment}', [TestCommentController::class, 'destroy']);

    Route::get('test-scenarios/{testScenario}/automated-tests', [AutomatedTestController::class, 'index']);
    Route::post('automated-tests', [AutomatedTestController::class, 'store']);
    Route::get('automated-tests/{automatedTest}', [AutomatedTestController::class, 'show']);
    Route::put('automated-tests/{automatedTest}', [AutomatedTestController::class, 'update']);
    Route::delete('automated-tests/{automatedTest}', [AutomatedTestController::class, 'destroy']);
});
