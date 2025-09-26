<?php

namespace App\QueryBuilders\Chocotissue;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder AS QueryBuilder;

class UserTopTissueQueryBuilder
{
    public function buildUserTissueQuery(
        QueryBuilder $tissueQuery,
        QueryBuilder $chocoMypageQuery,
        QueryBuilder $chocoGuestQuery
    ): QueryBuilder {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                'tissues.*',
                DB::raw("
                    CASE
                        WHEN choco_casts.id IS NOT NULL
                            THEN CONCAT('choco_cast-', choco_casts.id)
                        WHEN night_casts.id IS NOT NULL AND night_casts_binding_choco_casts.id IS NOT NULL
                            THEN CONCAT('choco_cast-', night_casts_binding_choco_casts.id)
                        WHEN night_casts.id IS NOT NULL
                            THEN CONCAT('night_cast-', night_casts.id)
                        WHEN choco_guests.id IS NOT NULL
                            THEN CONCAT('choco_guest-', choco_guests.id)
                        WHEN choco_mypages.id IS NOT NULL
                            THEN CONCAT('choco_mypage-', choco_mypages.id)
                    END AS user_id_for_grouping
                "),
                DB::raw("
                    CASE
                        WHEN choco_casts.id IS NOT NULL
                            THEN choco_casts.id
                        WHEN night_casts.id IS NOT NULL AND night_casts_binding_choco_casts.id IS NOT NULL
                            THEN night_casts_binding_choco_casts.id
                    END AS user_id_for_choco_cast
                "),
                DB::raw("
                    CASE
                        WHEN choco_casts.id IS NOT NULL
                            THEN choco_casts.shop_table_id
                        WHEN night_casts.id IS NOT NULL AND night_casts_binding_choco_casts.id IS NOT NULL
                            THEN night_casts_binding_choco_casts.shop_table_id
                    END AS choco_shop_table_id
                "),
                DB::raw("
                    CASE
                        WHEN choco_casts_binding_night_casts.id IS NOT NULL
                            THEN choco_casts_binding_night_casts.shop_id
                        WHEN night_casts.id IS NOT NULL
                            THEN night_casts.shop_id
                    END AS night_shop_table_id
                ")
            )
            ->fromSub($tissueQuery, 'tissues')
            ->leftJoin('casts AS choco_casts', 'choco_casts.id', '=', 'tissues.cast_id')
            ->leftJoin('yoasobi_casts AS night_casts', 'night_casts.id', '=', 'tissues.night_cast_id')
            ->leftJoin('casts AS night_casts_binding_choco_casts', 'night_casts_binding_choco_casts.town_night_cast_id', '=', 'night_casts.id')
            ->leftJoin('yoasobi_casts AS choco_casts_binding_night_casts', 'choco_casts_binding_night_casts.id', '=', 'choco_casts.town_night_cast_id')
            ->leftJoinSub($chocoMypageQuery, 'choco_mypages', 'choco_mypages.id', '=', 'tissues.mypage_id')
            ->leftJoinSub($chocoGuestQuery, 'choco_guests', 'choco_guests.id', '=', 'tissues.guest_id')
            ->whereNotNull('choco_casts.id')
            ->orWhereNotNull('night_casts.id')
            ->orWhereNotNull('choco_guests.id')
            ->orWhereNotNull('choco_mypages.id');
    }

    public function buildUserRankingOrderTissueQuery(
        QueryBuilder $userTissueQuery
    ): QueryBuilder {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                'tissues.*',
                DB::raw('
                    ROW_NUMBER() OVER (
                        PARTITION BY user_id_for_grouping
                        ORDER BY
                            (good_count + add_good_count) DESC,
                            view_count DESC
                    ) as show_num
                ')
            )
            ->fromSub($userTissueQuery, 'tissues');
    }

    public function buildShopRankingOrderTissueQuery(
        QueryBuilder $userTissueQuery,
        QueryBuilder $tissueCommentLastOneQuery
    ): QueryBuilder {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                'tissues.*',
                DB::raw("
                    ROW_NUMBER() OVER (
                        PARTITION BY user_id_for_grouping
                        ORDER BY
                            (
                                CASE
                                    WHEN last_tissue_comments.created_at IS NOT NULL AND last_tissue_comments.created_at > release_date THEN
                                        last_tissue_comments.created_at
                                    ELSE
                                        release_date
                                END
                            ) DESC
                    ) as show_num
                ")
            )
            ->fromSub($userTissueQuery, 'tissues')
            ->leftJoinSub(
                $tissueCommentLastOneQuery,
                'last_tissue_comments',
                'last_tissue_comments.tissue_id',
                '=',
                'tissues.id'
            );
    }
}
