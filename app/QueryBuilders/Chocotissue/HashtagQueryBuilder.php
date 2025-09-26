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
        ->select(
            "tissues.*",
            "last_tissue_comments.created_at AS last_comment_datetime",
        )
        ->fromSub($tissueQuery, 'tissues')
        ->leftJoin('casts AS choco_casts', 'choco_casts.id', '=', 'tissues.cast_id')
        ->leftJoin('yoasobi_casts AS night_casts', 'night_casts.id', '=', 'tissues.night_cast_id')
        ->leftJoinSub($chocoMypageQuery, 'choco_mypages', function ($join) {
            $join->on('tissues.mypage_id', '=', 'choco_mypages.id');
        })
        ->leftJoinSub($chocoGuestQuery, 'choco_guests', function ($join) {
            $join->on('tissues.guest_id', '=', 'choco_guests.id');
        })
        ->leftJoinSub(
            $tissueCommentLastOneQuery,
            'last_tissue_comments',
            'last_tissue_comments.tissue_id',
            '=',
            'tissues.id'
        )
        ->whereNotNull('choco_casts.id')
        ->orWhereNotNull('night_casts.id')
        ->orWhereNotNull('choco_guests.id')
        ->orWhereNotNull('choco_mypages.id');
    }

    public function buildTissueHashtag(
        QueryBuilder $hashtagQuery,
        QueryBuilder $eligibleTissueQuery
    ): QueryBuilder {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                "hashtags.id AS hashtag_id",
                "hashtags.name AS name",
                "hashtags.event_type AS event_type",
                "hashtags.add_count AS add_count",
                "tissues.view_count AS view_count",
                DB::raw("
                    (
                        CASE
                            WHEN tissues.last_comment_datetime IS NOT NULL THEN
                                tissues.last_comment_datetime
                            ELSE
                                tissues.release_date
                        END
                    ) AS last_update_datetime
                "),
                "tissues.id AS tissue_id"
            )
            ->fromSub($hashtagQuery, 'hashtags')
            ->rightJoinSub($eligibleTissueQuery, 'tissues', function ($join) {
                $join->on('hashtags.tissue_id', '=', 'tissues.id');
            })
            ->where('hashtags.active_flg', DB::raw(1));
    }

    public function buildTissueHashtagShowNum(
        QueryBuilder $tissueHashtagQuery
    ): QueryBuilder {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                '*',
                DB::raw("
                    ROW_NUMBER() OVER (
                        PARTITION BY tissue_id
                        ORDER BY
                            event_type DESC,
                            hashtag_id ASC
                    ) as show_num
                ")
            )
            ->fromSub($tissueHashtagQuery, 'tissue_hashtag');
    }

    public function buildRanking(
        QueryBuilder $tissueHashtagShowNumQuery
    ): QueryBuilder {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                "*",
                DB::raw("
                    ROW_NUMBER() OVER (
                        ORDER BY
                            event_type DESC,
                            show_num ASC,
                            last_update_datetime DESC,
                            tissue_id ASC
                    ) as order_num
                ")
            )
            ->fromSub($tissueHashtagShowNumQuery, 'tissue_shop_num_data');
    }
}
