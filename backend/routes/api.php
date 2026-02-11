<?php

use App\Http\Controllers\Api\Ta\TaApartmentsController;
use App\Http\Controllers\Api\Ta\TaBlocksController;
use App\Http\Controllers\Api\Ta\TaDirectoriesController;
use App\Http\Controllers\Api\Ta\TaUnitMeasurementsController;
use App\Http\Controllers\Api\TaUi\TaUiRefreshController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'time' => now()->toIso8601String(),
    ]);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| TA (TrendAgent) — read-only API from local ta_* tables
|--------------------------------------------------------------------------
| No calls to external TrendAgent API. For frontend.
*/
Route::prefix('ta')->group(function () {
    Route::get('blocks', [TaBlocksController::class, 'index']);
    Route::get('blocks/{block_id}', [TaBlocksController::class, 'show']);
    Route::post('blocks/{block_id}/refresh', [TaBlocksController::class, 'refresh'])
        ->middleware('internal.key');
    Route::get('apartments', [TaApartmentsController::class, 'index']);
    Route::get('apartments/{apartment_id}', [TaApartmentsController::class, 'show']);
    Route::post('apartments/{apartment_id}/refresh', [TaApartmentsController::class, 'refresh'])
        ->middleware('internal.key');
    Route::get('directories', [TaDirectoriesController::class, 'index']);
    Route::get('unit-measurements', [TaUnitMeasurementsController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| TA-UI — frontend proxy for refresh (no X-Internal-Key)
|--------------------------------------------------------------------------
| Rate-limited. Same effect as POST /api/ta/.../refresh.
*/
Route::prefix('ta-ui')->middleware('throttle:10,1')->group(function () {
    Route::post('blocks/{block_id}/refresh', [TaUiRefreshController::class, 'refreshBlock']);
    Route::post('apartments/{apartment_id}/refresh', [TaUiRefreshController::class, 'refreshApartment']);
});
