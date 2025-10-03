<?php

namespace App\QueryBuilders\Chocotissue;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder AS QueryBuilder;
use Illuminate\Database\Query\Expression;

class UserScoreQueryBuilder
{
    private const GROUP_KEY_CHOCO_CAST = 'choco_cast_';
    private const GROUP_KEY_NIGHT_CAST = 'night_cast_';
    private const GROUP_KEY_CHOCO_MYPAGE = 'choco_mypage_';
    private const GROUP_KEY_CHOCO_GUEST = 'choco_guest_';

    public function buildTissueQueryBuild(
        QueryBuilder $tissueQuery,
        QueryBuilder $tissueCommentQuery
    ): QueryBuilder {
        return DB::connection('mysql-chocolat')
            ->query()
            ->select(
                DB::raw('MAX(tissues.id) AS id'),
                DB::raw('MAX(tissues.cast_id) AS cast_id'),
                DB::raw('MAX(tissues.night_cast_id) AS night_cast_id'),
                DB::raw('MAX(tissues.mypage_id) AS mypage_id'),
                DB::raw('MAX(tissues.guest_id) AS guest_id'),
                DB::raw('MAX(tissues.release_date) AS release_date'),
                DB::raw('MAX(tissues.good_count) AS good_count'),
                DB::raw('MAX(tissues.add_good_count) AS add_good_count'),
                DB::raw('MAX(tissues.sns_count) AS sns_count'),
                DB::raw("COUNT(tissue_comments.id) AS comment_count")
            )
            ->fromSub($tissueQuery, 'tissues')
            ->leftJoinSub(
                $tissueCommentQuery,
                'tissue_comments',
                'tissue_comments.tissue_id',
                '=',
                'tissues.id'
            )
            ->groupBy('tissues.id');
    }

    public function buildWeeklyScoreQueryBuild(
        QueryBuilder $tissueQuery,
        QueryBuilder $weeklyOrTotalRankingPointQuery,
        QueryBuilder $chocoMypageQuery,
        QueryBuilder $chocoGuestQuery,
        Carbon $weekStartDate,
        Carbon $snsWeekStartDate
    ): QueryBuilder {
        return DB::connection('mysql-chocolat')
            ->query()
            ->select(
                DB::raw("MAX(choco_casts.id) AS choco_cast_id"),
                DB::raw("MAX(night_casts.id) AS night_cast_id "),
                DB::raw("MAX(choco_mypages.id) AS choco_mypage_id"),
                DB::raw("MAX(choco_guests.id) AS choco_guest_id"),
                DB::raw("MAX(COALESCE(night_casts_binding_choco_casts.shop_table_id, choco_casts.shop_table_id)) AS choco_shop_table_id"),
                DB::raw("MAX(night_casts.shop_id) AS night_shop_table_id"),
                DB::raw("
                    SUM(
                        CASE WHEN tissues.release_date >= '{$weekStartDate}' THEN (
                            tissues.good_count + tissues.add_good_count
                        ) ELSE 0 END
                    ) AS total_good_count
                "),
                DB::raw("
                    SUM(
                        CASE WHEN tissues.release_date >= '{$weekStartDate}' THEN (
                            tissues.comment_count
                        ) ELSE 0 END
                    ) AS total_comment_count
                "),
                DB::raw("
                    SUM(
                        CASE WHEN tissues.release_date >= '{$snsWeekStartDate}' THEN (
                            tissues.sns_count
                        ) ELSE 0 END
                    ) AS total_sns_count
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
            ->whereNotNull('choco_casts.id')
            ->orWhereNotNull('night_casts.id')
            ->orWhereNotNull('choco_guests.id')
            ->orWhereNotNull('choco_mypages.id')
            ->groupBy($this->getCanonicalUserGroupingExpression());
    }

    public function buildScoreQueryBuild(
        QueryBuilder $tissueQuery,
        QueryBuilder $weeklyOrTotalRankingPointQuery,
        QueryBuilder $chocoMypageQuery,
        QueryBuilder $chocoGuestQuery
    ): QueryBuilder {
        return DB::connection('mysql-chocolat')
            ->query()
            ->select(
                DB::raw("MAX(choco_casts.id) AS choco_cast_id"),
                DB::raw("MAX(night_casts.id) AS night_cast_id "),
                DB::raw("MAX(choco_mypages.id) AS choco_mypage_id"),
                DB::raw("MAX(choco_guests.id) AS choco_guest_id"),
                DB::raw("MAX(COALESCE(night_casts_binding_choco_casts.shop_table_id, choco_casts.shop_table_id)) AS choco_shop_table_id"),
                DB::raw("MAX(night_casts.shop_id) AS night_shop_table_id"),
                DB::raw("SUM(tissues.good_count + tissues.add_good_count) AS total_good_count"),
                DB::raw("SUM(tissues.comment_count) AS total_comment_count"),
                DB::raw("SUM(tissues.sns_count) AS total_sns_count"),
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
            ->whereNotNull('choco_casts.id')
            ->orWhereNotNull('night_casts.id')
            ->orWhereNotNull('choco_guests.id')
            ->orWhereNotNull('choco_mypages.id')
            ->groupBy($this->getCanonicalUserGroupingExpression());
    }

    public function castQueryBuild(
        QueryBuilder $tissueQuery,
        QueryBuilder $weeklyOrTotalRankingPointQuery
    ): QueryBuilder {
        return DB::connection('mysql-chocolat')
            ->query()
            ->select(
                DB::raw("MAX(choco_casts.id) AS choco_cast_id"),
                DB::raw("MAX(night_casts.id) AS night_cast_id "),
                DB::raw("MAX(COALESCE(night_casts_binding_choco_casts.shop_table_id, choco_casts.shop_table_id)) AS choco_shop_table_id"),
                DB::raw("MAX(night_casts.shop_id) AS night_shop_table_id"),
                DB::raw("SUM(tissues.good_count + tissues.add_good_count) AS total_good_count"),
                DB::raw("
                    COALESCE(
                        MAX(choco_cast_weekly_ranking_points.point),
                        MAX(night_cast_weekly_ranking_points.point),
                        0
                    ) AS rank_point
                "),
                DB::raw("
                    COALESCE(
                        MAX(choco_cast_weekly_ranking_points.tissue_count),
                        MAX(night_cast_weekly_ranking_points.tissue_count),
                        0
                    ) AS tissue_count
                "),
                DB::raw("MAX(tissues.id) AS last_tissue_id")
            )
            ->fromSub($tissueQuery, 'tissues')
            ->leftJoin('casts AS choco_casts', 'choco_casts.id', '=', 'tissues.cast_id')
            ->leftJoin('yoasobi_casts AS night_casts', 'night_casts.id', '=', 'tissues.night_cast_id')
            ->leftJoin('casts AS night_casts_binding_choco_casts', 'night_casts_binding_choco_casts.town_night_cast_id', '=', 'night_casts.id')
            ->leftJoinSub($weeklyOrTotalRankingPointQuery, 'choco_cast_weekly_ranking_points', 'choco_cast_weekly_ranking_points.choco_cast_id', '=', 'choco_casts.id')
            ->leftJoinSub($weeklyOrTotalRankingPointQuery, 'night_cast_weekly_ranking_points', 'night_cast_weekly_ranking_points.night_cast_id', '=', 'night_casts.id')
            ->whereNotNull('choco_casts.id')
            ->orWhereNotNull('night_casts.id')
            ->groupBy(DB::raw("
                CASE
                    WHEN night_casts_binding_choco_casts.id IS NOT NULL THEN
                        CONCAT('choco_cast_', night_casts_binding_choco_casts.id)
                    WHEN choco_casts.id IS NOT NULL THEN
                        CONCAT('choco_cast_', choco_casts.id)
                    WHEN night_casts.id IS NOT NULL THEN
                        CONCAT('night_cast_', night_casts.id)
                END
            "));
    }

    /**
     * Generates the raw SQL expression for grouping different user types into a canonical ID.
     * This is the core logic for user identity normalization in rankings.
     *
     * @return Expression
     */
    private function getCanonicalUserGroupingExpression(): Expression
    {
        $sql = "
            CASE
                -- A choco_cast bound to a night_cast takes precedence for grouping to unify identity.
                WHEN night_casts_binding_choco_casts.id IS NOT NULL THEN
                    CONCAT('" . self::GROUP_KEY_CHOCO_CAST . "', night_casts_binding_choco_casts.id)
                -- Standard choco_cast.
                WHEN choco_casts.id IS NOT NULL THEN
                    CONCAT('" . self::GROUP_KEY_CHOCO_CAST . "', choco_casts.id)
                -- Standard night_cast.
                WHEN night_casts.id IS NOT NULL THEN
                    CONCAT('" . self::GROUP_KEY_NIGHT_CAST . "', night_casts.id)
                -- Standard mypage user.
                WHEN choco_mypages.id IS NOT NULL THEN
                    CONCAT('" . self::GROUP_KEY_CHOCO_MYPAGE . "', choco_mypages.id)
                -- Standard guest user.
                WHEN choco_guests.id IS NOT NULL THEN
                    CONCAT('" . self::GROUP_KEY_CHOCO_GUEST . "', choco_guests.id)
            END";

        return DB::raw($sql);
    }
}
