<?php

namespace App\QueryBuilders\Chocotissue;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder AS QueryBuilder;
use Illuminate\Database\Eloquent\Builder AS EloquentBuilder;

class UserTopTissueQueryBuilder
{
    public function buildUserTissueQuery(
        QueryBuilder $tissueQuery,
        EloquentBuilder $chocoMypageQuery,
        EloquentBuilder $chocoGuestQuery
    ): QueryBuilder {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->fromSub($tissueQuery, 'tissues')
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
                "),
            )
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
            ->fromSub($userTissueQuery, 'tissues')
                ->crossJoin(DB::raw('(SELECT @test:=NULL, @num:=0) vars'))
                ->select(
                    'tissues.*',
                    DB::raw('@num := IF(@test = tissues.user_id_for_grouping, @num := @num + 1, 1) AS show_num'),
                    DB::raw('@test := tissues.user_id_for_grouping AS user_id_current')
                )
                ->orderBy('tissues.user_id_for_grouping', 'ASC')
                ->orderBy('tissues.set_top_status', 'DESC')
                ->orderBy(DB::raw('tissues.good_count + tissues.add_good_count'), 'DESC')
                ->orderBy('tissues.view_count', 'DESC');
    }

    public function buildShopRankingOrderTissueQuery(
        QueryBuilder $userTissueQuery
    ): QueryBuilder {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->fromSub($userTissueQuery, 'tissues')
            ->crossJoin(DB::raw('(SELECT @test:=NULL, @num:=0) vars'))
            ->select(
                'tissues.*',
                DB::raw('@num := IF(@test = tissues.user_id_for_grouping, @num := @num + 1, 1) AS show_num'),
                DB::raw('@test := tissues.user_id_for_grouping AS user_id_current')
            )
            ->orderBy('tissues.user_id_for_grouping', 'ASC')
            ->orderBy(DB::raw("(
                CASE
                    WHEN last_comment_date IS NOT NULL AND last_comment_date > release_date THEN
                        last_comment_date
                    ELSE
                        release_date
                END
            )"), "DESC");
    }

    public function buildShopRankingDetailOrderTissueQuery(
        QueryBuilder $userTissueQuery
    ): QueryBuilder {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->fromSub($userTissueQuery, 'tissues')
                ->crossJoin(DB::raw('(SELECT @test:=NULL, @num:=0) vars'))
                ->select(
                    'tissues.*',
                    DB::raw('@num := IF(@test = tissues.user_id_for_grouping, @num := @num + 1, 1) AS show_num'),
                    DB::raw('@test := tissues.user_id_for_grouping AS user_id_current')
                )
                ->orderBy('tissues.user_id_for_grouping', 'ASC')
                // ->orderBy('tissues.set_top_status', 'DESC')
                ->orderBy(DB::raw('tissues.good_count + tissues.add_good_count'), 'DESC')
                ->orderBy('user_tissues.view_count', 'DESC');
    }
}
