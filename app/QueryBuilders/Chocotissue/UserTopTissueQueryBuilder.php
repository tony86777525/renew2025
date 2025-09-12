<?php

namespace App\QueryBuilders\Chocotissue;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder AS QueryBuilder;
use Illuminate\Database\Eloquent\Builder AS EloquentBuilder;

class UserTopTissueQueryBuilder
{
    public function buildUserTissueQuery(
        EloquentBuilder $tissueQuery,
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
                ")
            )
            ->leftJoin('casts AS choco_casts', 'choco_casts.id', '=', 'tissues.cast_id')
            ->leftJoin('yoasobi_casts AS night_casts', 'night_casts.id', '=', 'tissues.night_cast_id')
            ->leftJoin('casts AS night_casts_binding_choco_casts', 'night_casts_binding_choco_casts.town_night_cast_id', '=', 'night_casts.id')
            ->leftJoinSub($chocoMypageQuery, 'choco_mypages', 'choco_mypages.id', '=', 'tissues.mypage_id')
            ->leftJoinSub($chocoGuestQuery, 'choco_guests', 'choco_guests.id', '=', 'tissues.guest_id')
            ->whereNotNull('choco_casts.id')
            ->orWhereNotNull('night_casts.id')
            ->orWhereNotNull('choco_guests.id')
            ->orWhereNotNull('choco_mypages.id');
    }

    public function buildOrderTissueQuery(
        QueryBuilder $userTissueQuery
    ): QueryBuilder {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->fromSub($userTissueQuery, 'user_tissues')
                ->crossJoin(DB::raw('(SELECT @test:=NULL, @num:=0) vars'))
                ->select(
                    'user_tissues.*',
                    DB::raw('@num := IF(@test = user_tissues.user_id_for_grouping, @num := @num + 1, 1) AS show_num'),
                    DB::raw('@test := user_tissues.user_id_for_grouping AS user_id_current')
                )
                ->orderBy('user_tissues.user_id_for_grouping', 'ASC')
                ->orderBy('user_tissues.set_top_status', 'DESC')
                ->orderBy(DB::raw('user_tissues.good_count + user_tissues.add_good_count'), 'DESC')
                ->orderBy('user_tissues.view_count', 'DESC');
    }
}
