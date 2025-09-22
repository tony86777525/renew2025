<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\TopController;
use App\Http\Controllers\User\ChocotissueController;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', [TopController::class, 'index'])
    ->name('user.top.index');

Route::get('/yc-championship', [ChocotissueController::class, 'handle'])
    ->name('user.chocotissue.recommendations');
Route::get('/yc-championship/timeline', [ChocotissueController::class, 'timeline'])
    ->name('user.chocotissue.list_timeline');
Route::get('/yc-championship/user-rankings', [ChocotissueController::class, 'userRankings'])
    ->name('user.chocotissue.list_user_rankings');
Route::get('/yc-championship/user-weekly-rankings', [ChocotissueController::class, 'userWeeklyRankings'])
    ->name('user.chocotissue.list_user_weekly_rankings');
Route::get('/yc-championship/shop-rankings', [ChocotissueController::class, 'shopRankings'])
    ->name('user.chocotissue.list_shop_rankings');
Route::get('/yc-championship/shop-ranking-detail', [ChocotissueController::class, 'shopRankingDetail'])
    ->name('user.chocotissue.list_user_rankings');
Route::get('/yc-championship/hashtags', [ChocotissueController::class, 'hashtags'])
    ->name('user.chocotissue.list_user_rankings');
Route::get('/yc-championship/hashtag-detail/{hashtag_id}/', [ChocotissueController::class, 'hashtagDetail'])
    ->name('user.chocotissue.list_user_rankings');
Route::get('/detail/{tissue_id}', [ChocotissueController::class, 'detail'])
    ->name('user.chocotissue.detail');
