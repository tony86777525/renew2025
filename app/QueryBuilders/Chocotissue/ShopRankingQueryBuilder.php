<?php

namespace App\QueryBuilders\Chocotissue;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder AS QueryBuilder;
use Illuminate\Database\Eloquent\Builder AS EloquentBuilder;

class ShopRankingQueryBuilder
{
    public function buildEligibleTissue(
        QueryBuilder $tissueQuery,
        EloquentBuilder $rankingPointQuery,
    ): QueryBuilder {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->fromSub(
                DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
                    ->query()
                    ->fromSub($tissueQuery, 'tissues')
                    ->leftJoin('casts AS choco_casts', 'choco_casts.id', '=', 'tissues.cast_id')
                    ->leftJoin('yoasobi_casts AS night_casts', 'night_casts.id', '=', 'tissues.night_cast_id')
                    ->leftJoin('casts AS night_casts_binding_choco_casts', 'night_casts_binding_choco_casts.town_night_cast_id', '=', 'night_casts.id')
                    ->leftJoinSub($rankingPointQuery, 'choco_cast_ranking_points', 'choco_cast_ranking_points.choco_cast_id', '=', 'choco_casts.id')
                    ->leftJoinSub($rankingPointQuery, 'night_cast_ranking_points', 'night_cast_ranking_points.night_cast_id', '=', 'night_casts.id')
                    ->select(
                        DB::raw("MAX(choco_casts.id) AS choco_cast_id"),
                        DB::raw("MAX(night_casts.id)  AS night_cast_id"),
                        DB::raw("MAX(COALESCE(night_casts_binding_choco_casts.shop_table_id, choco_casts.shop_table_id)) AS choco_shop_table_id"),
                        DB::raw("MAX(night_casts.shop_id) AS night_shop_table_id"),
                        DB::raw("COUNT(tissues.id) AS tissue_count"),
                        DB::raw("MAX(COALESCE(choco_cast_ranking_points.point, night_cast_ranking_points.point, 0)) AS point"),
                        DB::raw("MAX(tissues.id) AS id")
                    )
                    ->groupBy(DB::raw("
                        CASE
                            WHEN night_casts_binding_choco_casts.id IS NOT NULL THEN
                                CONCAT('choco_cast_', night_casts_binding_choco_casts.id)
                            WHEN choco_casts.id IS NOT NULL THEN
                                CONCAT('choco_cast_', choco_casts.id)
                            WHEN night_casts.id IS NOT NULL THEN
                                CONCAT('night_cast_', night_casts.id)
                        END
                    "))
            , 'tissues')
            ->leftJoin('shop_mains AS choco_shops', 'choco_shops.id', '=', 'tissues.choco_shop_table_id')
            ->leftJoin('yoasobi_shops_all AS choco_shops_binding_night_shops', 'choco_shops_binding_night_shops.id', '=', 'choco_shops.night_town_id')
            ->leftJoin('yoasobi_shops_all AS night_shops', 'night_shops.id', '=', 'tissues.night_shop_table_id')
            ->leftJoin('shop_mains AS night_shops_binding_choco_shops', 'night_shops_binding_choco_shops.night_town_id', '=', 'night_shops.id')
            ->select(
                'tissues.choco_cast_id',
                'tissues.night_cast_id',
                'choco_shops.id AS choco_shop_table_id',
                'choco_shops.pref_id AS choco_shop_pref_id',
                'choco_shops.active_flg AS choco_shop_active_flg',
                'choco_shops.close_flg AS choco_shop_close_flg',
                'choco_shops.test_shop AS choco_shop_test_shop',
                'choco_shops.pay_flg AS choco_shop_pay_flg',
                'choco_shops_binding_night_shops.id AS choco_shop_binding_night_shop_table_id',
                'choco_shops_binding_night_shops.prefecture_id AS choco_shop_binding_night_shop_pref_id',
                'choco_shops_binding_night_shops.status_id AS choco_shop_binding_night_shop_status_id',
                'choco_shops_binding_night_shops.is_closed AS choco_shop_binding_night_shop_is_closed',
                'choco_shops_binding_night_shops.is_test AS choco_shop_binding_night_shop_is_test',
                'choco_shops_binding_night_shops.plan AS choco_shop_binding_night_shop_plan',
                'night_shops_binding_choco_shops.id AS night_shop_binding_choco_shop_table_id',
                'night_shops_binding_choco_shops.pref_id AS night_shop_binding_choco_shop_pref_id',
                'night_shops_binding_choco_shops.active_flg AS night_shop_binding_choco_shop_active_flg',
                'night_shops_binding_choco_shops.close_flg AS night_shop_binding_choco_shop_close_flg',
                'night_shops_binding_choco_shops.test_shop AS night_shop_binding_choco_shop_test_shop',
                'night_shops_binding_choco_shops.pay_flg AS night_shop_binding_choco_shop_pay_flg',
                'night_shops.id AS night_shop_table_id',
                'night_shops.prefecture_id AS night_shop_pref_id',
                'night_shops.status_id AS night_shop_status_id',
                'night_shops.is_closed AS night_shop_is_closed',
                'night_shops.is_test AS night_shop_is_test',
                'night_shops.plan AS night_shop_plan',
                'tissues.point AS rank_point',
                'tissues.tissue_count AS tissue_count',
                'tissues.id AS id'
            );
    }

    public function buildTissueChocoShop(
        QueryBuilder $eligibleTissueQuery,
    ): QueryBuilder {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->fromSub($eligibleTissueQuery, 'tissueQuery')
            ->select(
                'choco_cast_id',
                'night_cast_id',
                'choco_shop_table_id AS choco_shop_table_id',
                'choco_shop_pref_id AS choco_shop_pref_id',
                'choco_shop_binding_night_shop_table_id AS night_shop_table_id',
                'choco_shop_binding_night_shop_pref_id AS night_shop_pref_id',
                'rank_point',
                'tissue_count',
                'id'
            )
            ->where(function ($query) {
                $query
                    ->whereNotNull('choco_shop_table_id')
                    ->orWhereNotNull('choco_shop_binding_night_shop_table_id');
            })
            ->where(function ($query) {
                $query
                    ->whereNotNull('choco_shop_table_id')
                    ->where('choco_shop_active_flg', DB::raw(1))
                    ->where('choco_shop_close_flg', DB::raw(0))
                    ->where('choco_shop_test_shop', DB::raw(0))
                    ->orWhereNotNull('choco_shop_binding_night_shop_table_id')
                    ->where('choco_shop_binding_night_shop_status_id', DB::raw(4))
                    ->where('choco_shop_binding_night_shop_is_closed', DB::raw(0))
                    ->where('choco_shop_binding_night_shop_is_test', DB::raw(0));
            });
    }

    public function buildTissueNightShop(
        QueryBuilder $eligibleTissueQuery,
    ): QueryBuilder {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->fromSub($eligibleTissueQuery, 'tissueQuery')
            ->select(
                'choco_cast_id',
                'night_cast_id',
                'night_shop_binding_choco_shop_table_id AS choco_shop_table_id',
                'night_shop_binding_choco_shop_pref_id AS choco_shop_pref_id',
                'night_shop_table_id AS night_shop_table_id',
                'night_shop_pref_id AS night_shop_pref_id',
                'rank_point',
                'tissue_count',
                'id'
            )
            ->where(function ($query) {
                $query
                    ->whereNotNull('night_shop_binding_choco_shop_table_id')
                    ->orWhereNotNull('night_shop_table_id');
            })
            ->where(function ($query) {
                $query
                    ->whereNotNull('night_shop_binding_choco_shop_table_id')
                    ->where('night_shop_binding_choco_shop_active_flg', DB::raw(1))
                    ->where('night_shop_binding_choco_shop_close_flg', DB::raw(0))
                    ->where('night_shop_binding_choco_shop_test_shop', DB::raw(0))
                    ->orWhereNotNull('night_shop_table_id')
                    ->where('night_shop_status_id', DB::raw(4))
                    ->where('night_shop_is_closed', DB::raw(0))
                    ->where('night_shop_is_test', DB::raw(0));
            });
    }

    public function buildUnpivoted(
        QueryBuilder $tissueChocoShopQuery,
        QueryBuilder $tissueNightShopQuery
    ): QueryBuilder {
        return $tissueChocoShopQuery->unionAll($tissueNightShopQuery);
    }

    public function buildUniqueCastShopPoints(
        QueryBuilder $unpivotedShopQuery,
    ): QueryBuilder {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->fromSub($unpivotedShopQuery, 'unpivoted_shops')
            ->select(
                'choco_shop_table_id',
                'choco_shop_pref_id',
                'night_shop_table_id',
                'night_shop_pref_id',
                'rank_point',
                'tissue_count',
                DB::raw("CONCAT(IFNULL(choco_shop_table_id, 0), '-', IFNULL(night_shop_table_id, 0)) AS canonical_shop_id"),
                DB::raw("CONCAT(IFNULL(choco_cast_id, 0),'-',IFNULL(night_cast_id, 0)) AS canonical_cast_id"),
            )
            ->distinct();
    }
}
