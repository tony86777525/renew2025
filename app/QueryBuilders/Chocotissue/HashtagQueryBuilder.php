<?php

namespace App\QueryBuilders\Chocotissue;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder AS QueryBuilder;

class HashtagQueryBuilder
{
    public function buildEligibleTissue(
        QueryBuilder $tissueQuery,
        QueryBuilder $chocoMypageQuery,
        QueryBuilder $chocoGuestQuery,
        QueryBuilder $tissueCommentLastOneQuery
    ): QueryBuilder {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
        ->query()
        ->fromSub($tissueQuery, 'tissues')
        ->select(
            "tissues.*",
            DB::raw("({$tissueCommentLastOneQuery->toSql()}) AS last_comment_date")
        )
        ->leftJoin('casts AS choco_casts', 'choco_casts.id', '=', 'tissues.cast_id')
        ->leftJoin('yoasobi_casts AS night_casts', 'night_casts.id', '=', 'tissues.night_cast_id')
        ->leftJoinSub($chocoMypageQuery, 'choco_mypages', function ($join) {
            $join->on('tissues.mypage_id', '=', 'choco_mypages.id');
        })
        ->leftJoinSub($chocoGuestQuery, 'choco_guests', function ($join) {
            $join->on('tissues.guest_id', '=', 'choco_guests.id');
        })
        ->whereNotNull('choco_casts.id')
        ->orWhereNotNull('night_casts.id')
        ->orWhereNotNull('choco_guests.id')
        ->orWhereNotNull('choco_mypages.id');
    }

    public function buildHashtagWithTissue(
        QueryBuilder $hashtagQuery,
        QueryBuilder $eligibleTissueQuery
    ): QueryBuilder {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->fromSub($hashtagQuery, 'hashtags')
            ->rightJoinSub($eligibleTissueQuery, 'tissues', function ($join) {
                $join->on('hashtags.tissue_id', '=', 'tissues.id');
            })
            ->select(
                "hashtags.id AS hashtag_id",
                "hashtags.name AS name",
                "hashtags.event_type AS event_type",
                "hashtags.add_count AS add_count",
                "tissues.view_count AS view_count",
                DB::raw("
                    (
                        CASE
                            WHEN tissues.last_comment_date IS NOT NULL THEN
                                tissues.last_comment_date
                            ELSE
                                tissues.release_date
                        END
                    ) AS last_update_datetime
                "),
                "tissues.id AS tissue_id"
            )
            ->where('hashtags.active_flg', DB::raw(1));
    }
}
