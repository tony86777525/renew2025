<?php

namespace App\Traits\Chocotissue;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Chocolat\MypageMain;
use App\Models\Chocolat\Guest;
use App\Models\Chocolat\ShopMain;
use App\Models\Chocolat\YoasobiShopsAll;
use App\Models\Chocolat\RankingPoint;
use App\Models\Chocolat\WeeklyRankingPoint;
use App\Models\Chocolat\Tissue;
use App\Models\Chocolat\TissueNightOsusumeActiveView;

trait CommonQueries
{
    protected function buildChocoMypageQuery()
    {
        return MypageMain::query()
            ->select('mypage_mains.id')
            ->rightJoin('mypages', 'mypages.id', '=', 'mypage_mains.id')
            ->where('mypages.is_usable', DB::raw(1))
            ->where('mypage_mains.active_flg', DB::raw(1));
    }

    protected function buildChocoGuestQuery()
    {
        return Guest::query()
            ->select('id')
            ->where('hide_flg', DB::raw(0));
    }

    protected function buildChocoShopQuery()
    {
        return ShopMain::query()
            ->select(
                'shop_mains.id',
                'area_prefs.area_id AS pref_id'
            )
            ->rightJoin('area_prefs', 'area_prefs.area_id', 'shop_mains.pref_id');
    }

    protected function buildNightShopQuery()
    {
        return YoasobiShopsAll::query()
            ->select(
                'yoasobi_shops_all.id',
                'area_prefs.area_id AS pref_id'
            )
            ->rightJoin('area_prefs', 'area_prefs.area_id', 'yoasobi_shops_all.prefecture_id');
    }

    protected function buildWeeklyRankingPointQuery(Carbon $date)
    {
        return WeeklyRankingPoint::query()
            ->select(
                'choco_cast_id',
                'night_cast_id',
                DB::raw("CASE WHEN tissue_from_type = '" . Tissue::TISSUE_FROM_TYPE_GIRL_MYPAGE . "' THEN post_user_id END AS choco_mypage_id"),
                DB::raw("CASE WHEN tissue_from_type = '" . Tissue::TISSUE_FROM_TYPE_GIRL_GUEST . "' THEN post_user_id END AS choco_guest_id"),
                'tissue_from_type',
                'point',
                'tissue_count'
            )
            ->where('is_aggregated', '=', DB::raw('1'))
            ->where('ranking_cumulative_start_date', $date);
    }

    protected function buildRankingPointQuery(Carbon $date)
    {
        return RankingPoint::query()
            ->select(
                'choco_cast_id',
                'night_cast_id',
                DB::raw("CASE WHEN tissue_from_type = '" . Tissue::TISSUE_FROM_TYPE_GIRL_MYPAGE . "' THEN post_user_id END AS choco_mypage_id"),
                DB::raw("CASE WHEN tissue_from_type = '" . Tissue::TISSUE_FROM_TYPE_GIRL_GUEST . "' THEN post_user_id END AS choco_guest_id"),
                'tissue_from_type',
                'total_point AS point',
                DB::raw("chocolat_tissue_count + yoasobi_tissue_count AS tissue_count")
            )
            ->where('is_valid', '=', DB::raw('1'))
            ->where('championship_start_date', $date);
    }

    protected function buildOsusumeTissueQuery(Carbon $startDate, Carbon $endDate)
    {
        return TissueNightOsusumeActiveView::query()
            ->whereBetween('release_date', [$startDate, $endDate]);
    }

    protected function buildUserTissueQuery(Carbon $startDate, Carbon $endDate)
    {
        return Tissue::query()
            ->select(
                'id',
                'cast_id',
                'night_cast_id',
                'mypage_id',
                'guest_id',
                'good_count',
                'add_good_count',
                'view_count',
                'set_top_status',
                'release_date'
            )
            ->where('published_flg', DB::raw(Tissue::PUBLISHED_FLG_TRUE))
            ->where('tissue_status', DB::raw(Tissue::TISSUE_STATUS_NORMAL))
            ->whereBetween('release_date', [$startDate, $endDate])
            ->where(function ($query) {
                $query
                    ->whereNotNull('cast_id')
                    ->whereNotIn('cast_id', $this->excludedChocoCasts())
                    ->orWhereNotNull('mypage_id')
                    ->orWhereNotNull('night_cast_id')
                    ->orWhereNotNull('guest_id')
                    ->whereNotIn('guest_id',  $this->excludedChocoGuests());
            });
    }

    protected function buildShopTissueQuery(Carbon $startDate, Carbon $endDate)
    {
        return Tissue::query()
            ->select(
                'id',
                'cast_id',
                'night_cast_id',
            )
            ->where('published_flg', DB::raw(Tissue::PUBLISHED_FLG_TRUE))
            ->where('tissue_status', DB::raw(Tissue::TISSUE_STATUS_NORMAL))
            ->whereBetween('release_date', [$startDate, $endDate])
            ->where(function ($query) {
                $query
                    ->whereNotNull('cast_id')
                    ->orWhereNotNull('night_cast_id');
            });
    }
}
