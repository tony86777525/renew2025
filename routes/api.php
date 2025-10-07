<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\Api\GetTissueController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// 建議的 API 路由結構
Route::prefix('v1/yc-championship')->group(function () {
    // 使用 GET 方法，因為是讀取資料
    // 路由名稱更具描述性
    Route::post('/tissues/recommendations', [GetTissueController::class, 'getRecommendationTissues'])
        ->name('api.tissues.recommendations');
});
