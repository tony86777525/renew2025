<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\Api\GetTissueController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1/yc-championship')->group(function () {
    Route::post('/tissues/recommendation', [GetTissueController::class, 'getRecommendationTissues'])
        ->name('api.tissues.recommendation');
    Route::post('/tissues/user-ranking', [GetTissueController::class, 'getUserRankingTissues'])
        ->name('api.tissues.user_ranking');
    Route::post('/tissues/user-ranking-weekly', [GetTissueController::class, 'getUserRankingWeeklyTissues'])
        ->name('api.tissues.user_ranking_weekly');
    Route::post('/tissues/shop-ranking', [GetTissueController::class, 'getShopRankingTissues'])
        ->name('api.tissues.shop_ranking');
    Route::post('/tissues/liked', [GetTissueController::class, 'getLikedTissues'])
        ->name('api.tissues.liked');
});
