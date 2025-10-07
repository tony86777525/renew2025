<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\ChocotissueController;

Route::prefix('yc-championship')->controller(ChocotissueController::class)->group(function () {
    Route::get('/timeline/{pref_id?}', 'timeline')
        ->name('user.chocotissue.list_timeline');
    Route::get('/user-rankings/{pref_id?}', 'userRankings')
        ->name('user.chocotissue.list_user_rankings');
    Route::get('/user-weekly-rankings/{pref_id?}', 'userWeeklyRankings')
        ->name('user.chocotissue.list_user_weekly_rankings');
    Route::get('/shop-rankings/{pref_id?}', 'shopRankings')
        ->name('user.chocotissue.list_shop_rankings');
    Route::get('/shop-ranking-detail', 'shopRankingDetail')
        ->name('user.chocotissue.shop_ranking_detail');
    Route::get('/hashtags', 'hashtags')
        ->name('user.chocotissue.hashtags');
    Route::get('/hashtag-detail/{hashtag_id}', 'hashtagDetail')
        ->name('user.chocotissue.hashtag_detail');
    Route::get('/detail/{tissue_id}', 'detail')
        ->name('user.chocotissue.detail');
    Route::get('/{pref_id?}', 'handle')
        ->name('user.chocotissue.recommendations');
});
