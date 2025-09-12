<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\TopController;
use App\Http\Controllers\User\ChocotissueController;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', [TopController::class, 'index'])
    ->name('user.top.index');

Route::get('/yc-championship', [ChocotissueController::class, 'recommendations'])
    ->name('user.chocotissue.recommendations');
Route::get('/yc-championship/timeline', [ChocotissueController::class, 'timeline'])
->name('user.chocotissue.list_timeline');
Route::get('/yc-championship/user-weekly-rankings', [ChocotissueController::class, 'userWeeklyRankings'])
->name('user.chocotissue.list_user_weekly_rankings');
Route::get('/yc-championship/user-rankings', [ChocotissueController::class, 'userRankings'])
    ->name('user.chocotissue.list_user_rankings');
Route::get('/yc-championship/shop-rankings', [ChocotissueController::class, 'shopRankings'])
    ->name('user.chocotissue.list_shop_rankings');

Route::get('/yc-championship/liked-items', [ChocotissueController::class, 'list_liked_items'])
    ->name('user.chocotissue.list_liked_items');
Route::get('/yc-championship/detail', [ChocotissueController::class, 'detail'])
    ->name('user.chocotissue.detail');
