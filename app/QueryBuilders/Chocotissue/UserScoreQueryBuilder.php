<?php

namespace App\QueryBuilders\Chocotissue;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder AS QueryBuilder;
use Illuminate\Database\Eloquent\Builder AS EloquentBuilder;

class UserScoreQueryBuilder
{
    public function build(
        QueryBuilder $tissueQuery,
        EloquentBuilder $weeklyOrTotalRankingPointQuery,
        EloquentBuilder $chocoMypageQuery,
        EloquentBuilder $chocoGuestQuery,
        Carbon $date
    ): QueryBuilder {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->fromSub($tissueQuery, 'tissues')
            ->leftJoin('casts AS choco_casts', 'choco_casts.id', '=', 'tissues.cast_id')
            ->leftJoin('yoasobi_casts AS night_casts', 'night_casts.id', '=', 'tissues.night_cast_id')
            ->leftJoin('casts AS night_casts_binding_choco_casts', 'night_casts_binding_choco_casts.town_night_cast_id', '=', 'night_casts.id')
            ->leftJoinSub($chocoMypageQuery, 'choco_mypages', 'choco_mypages.id', '=', 'tissues.mypage_id')
            ->leftJoinSub($chocoGuestQuery, 'choco_guests', 'choco_guests.id', '=', 'tissues.guest_id')
            ->leftJoinSub($weeklyOrTotalRankingPointQuery, 'choco_cast_weekly_ranking_points', 'choco_cast_weekly_ranking_points.choco_cast_id', '=', 'choco_casts.id')
            ->leftJoinSub($weeklyOrTotalRankingPointQuery, 'night_cast_weekly_ranking_points', 'night_cast_weekly_ranking_points.night_cast_id', '=', 'night_casts.id')
            ->leftJoinSub($weeklyOrTotalRankingPointQuery, 'choco_mypage_weekly_ranking_points', 'choco_mypage_weekly_ranking_points.choco_mypage_id', '=', 'choco_mypages.id')
            ->leftJoinSub($weeklyOrTotalRankingPointQuery, 'choco_guest_weekly_ranking_points', 'choco_guest_weekly_ranking_points.choco_guest_id', '=', 'choco_guests.id')
            ->select(
                DB::raw("MAX(choco_casts.id) AS choco_cast_id"),
                DB::raw("MAX(night_casts.id) AS night_cast_id "),
                DB::raw("MAX(choco_mypages.id) AS choco_mypage_id"),
                DB::raw("MAX(choco_guests.id) AS choco_guest_id"),
                DB::raw("MAX(COALESCE(night_casts_binding_choco_casts.shop_table_id, choco_casts.shop_table_id)) AS choco_shop_table_id"),
                DB::raw("MAX(night_casts.shop_id) AS night_shop_table_id"),
                DB::raw("
                    SUM(
                        CASE WHEN tissues.release_date >= '" . $date . "' THEN (
                            tissues.good_count + tissues.add_good_count
                        ) ELSE 0 END
                    ) AS total_good_count
                "),
                DB::raw("
                    COALESCE(
                        MAX(choco_cast_weekly_ranking_points.point),
                        MAX(night_cast_weekly_ranking_points.point),
                        MAX(choco_mypage_weekly_ranking_points.point),
                        MAX(choco_guest_weekly_ranking_points.point),
                        0
                    ) AS point
                "),
                DB::raw("
                    COALESCE(
                        MAX(choco_cast_weekly_ranking_points.tissue_count),
                        MAX(night_cast_weekly_ranking_points.tissue_count),
                        MAX(choco_mypage_weekly_ranking_points.tissue_count),
                        MAX(choco_guest_weekly_ranking_points.tissue_count),
                        0
                    ) AS tissue_count
                "),
                DB::raw("MAX(tissues.id) AS last_tissue_id")
            )
            ->whereNotNull('choco_casts.id')
            ->orWhereNotNull('night_casts.id')
            ->orWhereNotNull('choco_guests.id')
            ->orWhereNotNull('choco_mypages.id')
            ->groupBy(DB::raw("
                CASE
                    WHEN night_casts_binding_choco_casts.id IS NOT NULL THEN
                        CONCAT('choco_cast_', night_casts_binding_choco_casts.id)
                    WHEN choco_casts.id IS NOT NULL THEN
                        CONCAT('choco_cast_', choco_casts.id)
                    WHEN night_casts.id IS NOT NULL THEN
                        CONCAT('night_cast_', night_casts.id)
                    WHEN choco_mypages.id IS NOT NULL THEN
                        CONCAT('choco_mypage', choco_mypages.id)
                    WHEN choco_guests.id IS NOT NULL THEN
                        CONCAT('choco_guest', choco_guests.id)
                END
            "));
    }
}
