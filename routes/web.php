<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\ChocotissueController;

Route::get('/yc-championship/timeline/{pref_id?}', [ChocotissueController::class, 'timeline'])
    ->name('user.chocotissue.list_timeline');
Route::get('/yc-championship/user-rankings/{pref_id?}', [ChocotissueController::class, 'userRankings'])
    ->name('user.chocotissue.list_user_rankings');
Route::get('/yc-championship/user-weekly-rankings/{pref_id?}', [ChocotissueController::class, 'userWeeklyRankings'])
    ->name('user.chocotissue.list_user_weekly_rankings');
Route::get('/yc-championship/shop-rankings/{pref_id?}', [ChocotissueController::class, 'shopRankings'])
    ->name('user.chocotissue.list_shop_rankings');
Route::get('/yc-championship/shop-ranking-detail', [ChocotissueController::class, 'shopRankingDetail'])
    ->name('user.chocotissue.list_user_rankings');
Route::get('/yc-championship/hashtags', [ChocotissueController::class, 'hashtags'])
    ->name('user.chocotissue.list_user_rankings');
Route::get('/yc-championship/hashtag-detail/{hashtag_id}', [ChocotissueController::class, 'hashtagDetail'])
    ->name('user.chocotissue.list_user_rankings');
Route::get('/yc-championship/detail/{tissue_id}', [ChocotissueController::class, 'detail'])
    ->name('user.chocotissue.detail');
Route::get('/yc-championship/{pref_id?}', [ChocotissueController::class, 'handle'])
    ->name('user.chocotissue.recommendations');
