<?php

namespace App\QueryBuilders\Chocotissue;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder AS QueryBuilder;
use Illuminate\Database\Query\Expression;

class ShopRankingQueryBuilder
{
    private const GROUP_KEY_CHOCO_CAST = 'choco_cast_';
    private const GROUP_KEY_NIGHT_CAST = 'night_cast_';

    /**
     * Build a grouped query joining tissues with related casts and ranking points.
     *
     * @param QueryBuilder $tissueQuery
     * @param QueryBuilder $rankingPointQuery
     * @return QueryBuilder
     */
    public function buildCast(
        QueryBuilder $tissueQuery,
        QueryBuilder $rankingPointQuery
    ) {
        return DB::connection('mysql-chocolat')
            ->query()
            ->select(
                DB::raw("MAX(choco_casts.id) AS choco_cast_id"),
                DB::raw("MAX(night_casts.id)  AS night_cast_id"),
                DB::raw("MAX(COALESCE(night_casts_binding_choco_casts.shop_table_id, choco_casts.shop_table_id)) AS choco_shop_table_id"),
                DB::raw("MAX(night_casts.shop_id) AS night_shop_table_id"),
                DB::raw("COUNT(tissues.id) AS tissue_count"),
                DB::raw("MAX(COALESCE(choco_cast_ranking_points.point, night_cast_ranking_points.point, 0)) AS point"),
                DB::raw("MAX(tissues.id) AS id"),
            )
            ->fromSub($tissueQuery, 'tissues')
            ->leftJoin('casts AS choco_casts', 'choco_casts.id', '=', 'tissues.cast_id')
            ->leftJoin('yoasobi_casts AS night_casts', 'night_casts.id', '=', 'tissues.night_cast_id')
            ->leftJoin('casts AS night_casts_binding_choco_casts', 'night_casts_binding_choco_casts.town_night_cast_id', '=', 'night_casts.id')
            ->leftJoinSub($rankingPointQuery, 'choco_cast_ranking_points', 'choco_cast_ranking_points.choco_cast_id', '=', 'choco_casts.id')
            ->leftJoinSub($rankingPointQuery, 'night_cast_ranking_points', 'night_cast_ranking_points.night_cast_id', '=', 'night_casts.id')
            ->groupBy($this->getCastGroupingExpression());
    }

    /**
     * Build a grouped query joining casts with related shops.
     *
     * @param QueryBuilder $castQuery Base query for tissues within the championship period.
     * @return QueryBuilder
     */
    public function buildShop(
        QueryBuilder $castQuery
    ): QueryBuilder {
        return DB::connection('mysql-chocolat')
            ->query()
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
            )
            ->fromSub($castQuery, 'tissues')
            ->leftJoin('shop_mains AS choco_shops', 'choco_shops.id', '=', 'tissues.choco_shop_table_id')
            ->leftJoin('yoasobi_shops_all AS choco_shops_binding_night_shops', 'choco_shops_binding_night_shops.id', '=', 'choco_shops.night_town_id')
            ->leftJoin('yoasobi_shops_all AS night_shops', 'night_shops.id', '=', 'tissues.night_shop_table_id')
            ->leftJoin('shop_mains AS night_shops_binding_choco_shops', 'night_shops_binding_choco_shops.night_town_id', '=', 'night_shops.id');
    }

    /**
     * Build a query for eligible choco shops linked to tissues.
     *
     * @param QueryBuilder $eligibleTissueQuery
     * @return QueryBuilder
     */
    public function buildTissueChocoShop(
        QueryBuilder $eligibleTissueQuery,
    ): QueryBuilder {
        return DB::connection('mysql-chocolat')
            ->query()
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
            ->fromSub($eligibleTissueQuery, 'tissueQuery')
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

    /**
     * Build a query for eligible night shops linked to tissues.
     *
     * @param QueryBuilder $eligibleTissueQuery
     * @return QueryBuilder
     */
    public function buildTissueNightShop(
        QueryBuilder $eligibleTissueQuery
    ): QueryBuilder {
        return DB::connection('mysql-chocolat')
            ->query()
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
            ->fromSub($eligibleTissueQuery, 'tissueQuery')
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

    /**
     * Build a union query combining tissue records from both ChocoShop and NightShop.
     *
     * @param QueryBuilder $tissueChocoShopQuery
     * @param QueryBuilder $tissueNightShopQuery
     * @return QueryBuilder
     */
    public function buildUnionQuery(
        QueryBuilder $tissueChocoShopQuery,
        QueryBuilder $tissueNightShopQuery
    ): QueryBuilder {
        return $tissueChocoShopQuery->unionAll($tissueNightShopQuery);
    }

    /**
     * Build a query for unique cast shops.
     *
     * @param QueryBuilder $unionShopQuery
     * @return QueryBuilder
     */
    public function buildUniqueCastShopPoint(
        QueryBuilder $unionShopQuery
    ): QueryBuilder {
        return DB::connection('mysql-chocolat')
            ->query()
            ->select(
                'choco_shop_table_id',
                'choco_shop_pref_id',
                'night_shop_table_id',
                'night_shop_pref_id',
                'rank_point',
                'tissue_count',
                DB::raw("CONCAT(IFNULL(choco_shop_table_id, 0), '-', IFNULL(night_shop_table_id, 0)) AS canonical_shop_id"),
                DB::raw("CONCAT(IFNULL(choco_cast_id, 0),'-',IFNULL(night_cast_id, 0)) AS canonical_cast_id")
            )
            ->fromSub($unionShopQuery, 'union_shops')
            ->distinct();
    }

    /**
     * Build a query for shop point.
     *
     * @param QueryBuilder $uniqueCastShopPointQuery
     * @return QueryBuilder
     */
    public function buildShopPoint(
        QueryBuilder $uniqueCastShopPointQuery
    ): QueryBuilder {
        return DB::connection('mysql-chocolat')
            ->query()
            ->select(
                DB::raw("MAX(choco_shop_table_id) AS choco_shop_table_id"),
                DB::raw("MAX(choco_shop_pref_id) AS choco_shop_pref_id"),
                DB::raw("MAX(night_shop_table_id) AS night_shop_table_id"),
                DB::raw("MAX(night_shop_pref_id) AS night_shop_pref_id"),
                DB::raw("SUM(rank_point) AS rank_point"),
                DB::raw("SUM(tissue_count) AS tissue_count"),
                'canonical_shop_id',
                DB::raw("GROUP_CONCAT(canonical_cast_id SEPARATOR ', ') AS cast_ids")
            )
            ->fromSub($uniqueCastShopPointQuery, 'unique_cast_shop_points')
            ->groupBy('canonical_shop_id');
    }

    /**
     * Build a query for shop rank.
     *
     * @param QueryBuilder $shopPointQuery
     * @return QueryBuilder
     */
    public function buildShopRank(
        QueryBuilder $shopPointQuery
    ): QueryBuilder {
        return DB::connection('mysql-chocolat')
            ->query()
            ->select(
                'shop_point_data.*',
                DB::raw("
                    ROW_NUMBER() OVER (
                        ORDER BY
                            rank_point DESC,
                            tissue_count DESC,
                            choco_shop_table_id ASC,
                            night_shop_table_id ASC
                    ) as rank_num
                "),
                DB::raw("
                    ROW_NUMBER() OVER (
                        PARTITION BY
                            CASE
                                WHEN choco_shop_pref_id IS NOT NULL THEN
                                    choco_shop_pref_id
                                ELSE
                                    night_shop_pref_id
                            END
                        ORDER BY
                            rank_point DESC,
                            tissue_count DESC,
                            choco_shop_table_id ASC,
                            night_shop_table_id ASC
                    ) as pref_rank_num
                ")
            )
            ->fromSub($shopPointQuery, 'shop_point_data');
    }

    /**
     * Generates the raw SQL expression for grouping different cast types into a canonical ID.
     *
     * @return Expression
     */
    private function getCastGroupingExpression(): Expression
    {
        $sql = "
             CASE
                 -- A choco_cast bound to a night_cast takes precedence.
                 WHEN night_casts_binding_choco_casts.id IS NOT NULL THEN
                     CONCAT('" . self::GROUP_KEY_CHOCO_CAST . "', night_casts_binding_choco_casts.id)
                 -- Standard choco_cast.
                 WHEN choco_casts.id IS NOT NULL THEN
                     CONCAT('" . self::GROUP_KEY_CHOCO_CAST . "', choco_casts.id)
                 -- Standard night_cast.
                 WHEN night_casts.id IS NOT NULL THEN
                     CONCAT('" . self::GROUP_KEY_NIGHT_CAST . "', night_casts.id)
             END";

        return DB::raw($sql);
    }
}
