<?php

namespace App\Traits\Chocotissue;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Chocolat\MypageMain;
use App\Models\Chocolat\Guest;
use App\Models\Chocolat\ShopMain;
use App\Models\Chocolat\YoasobiShopsAll;
use App\Models\Chocolat\Tissue;
use App\Models\Chocolat\TissueNightOsusumeActiveView;
use App\Models\Chocolat\TissueComment;
use App\Models\Chocolat\RankingPoint;
use App\Models\Chocolat\WeeklyRankingPoint;
use App\Models\Chocolat\Hashtag;

trait CommonQueries
{
    protected function buildChocoMypageQuery()
    {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select('mypage_mains.id')
            ->from((new MypageMain)->getTable())
            ->rightJoin('mypages', 'mypages.id', '=', 'mypage_mains.id')
            ->where('mypages.is_usable', DB::raw(1))
            ->where('mypage_mains.active_flg', DB::raw(1));
    }

    protected function buildChocoGuestQuery()
    {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select('id')
            ->from((new Guest)->getTable())
            ->where('hide_flg', DB::raw(0));
    }

    protected function buildChocoShopQuery()
    {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                'shop_mains.id',
                'area_prefs.area_id AS pref_id'
            )
            ->from((new ShopMain)->getTable())
            ->rightJoin('area_prefs', 'area_prefs.area_id', 'shop_mains.pref_id');
    }

    protected function buildNightShopQuery()
    {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                'yoasobi_shops_all.id',
                'area_prefs.area_id AS pref_id'
            )
            ->from((new YoasobiShopsAll)->getTable())
            ->rightJoin('area_prefs', 'area_prefs.area_id', 'yoasobi_shops_all.prefecture_id');
    }

    protected function buildWeeklyRankingPointQuery(Carbon $date)
    {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                'choco_cast_id',
                'night_cast_id',
                DB::raw("
                    CASE
                        WHEN tissue_from_type = '" . Tissue::TISSUE_FROM_TYPE_GIRL_MYPAGE . "' THEN
                            post_user_id
                    END AS choco_mypage_id
                "),
                DB::raw("
                    CASE
                        WHEN tissue_from_type = '" . Tissue::TISSUE_FROM_TYPE_GIRL_GUEST . "' THEN
                            post_user_id
                    END AS choco_guest_id
                "),
                'tissue_from_type',
                'point',
                'tissue_count'
            )
            ->from((new WeeklyRankingPoint)->getTable())
            ->where('is_aggregated', '=', DB::raw('1'))
            ->where('ranking_cumulative_start_date', $date);
    }

    protected function buildRankingPointQuery(Carbon $date)
    {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                'id',
                'choco_cast_id',
                'night_cast_id',
                DB::raw("CASE WHEN tissue_from_type = '" . Tissue::TISSUE_FROM_TYPE_GIRL_MYPAGE . "' THEN post_user_id END AS choco_mypage_id"),
                DB::raw("CASE WHEN tissue_from_type = '" . Tissue::TISSUE_FROM_TYPE_GIRL_GUEST . "' THEN post_user_id END AS choco_guest_id"),
                'tissue_from_type',
                'total_point AS point',
                DB::raw("chocolat_tissue_count + yoasobi_tissue_count AS tissue_count")
            )
            ->from((new RankingPoint)->getTable())
            ->where('is_valid', '=', DB::raw('1'))
            ->where('championship_start_date', $date);
    }

    protected function buildOsusumeTissueQuery(Carbon $startDate, Carbon $endDate)
    {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->from((new TissueNightOsusumeActiveView)->getTable())
            ->where('published_flg', DB::raw(Tissue::PUBLISHED_FLG_TRUE))
            ->where('tissue_status', DB::raw(Tissue::TISSUE_STATUS_NORMAL))
            ->whereBetween('release_date', [$startDate, $endDate]);
    }

    protected function buildUserTissueQuery(
        Carbon $startDate,
        Carbon $endDate,
        array $excludedChocoCasts = [],
        array $excludedChocoGuests = []
    ) {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select('*')
            ->from((new Tissue)->getTable(), 'tissues')
            ->where('published_flg', DB::raw(Tissue::PUBLISHED_FLG_TRUE))
            ->where('tissue_status', DB::raw(Tissue::TISSUE_STATUS_NORMAL))
            ->whereBetween('release_date', [$startDate, $endDate])
            ->where(function ($query) {
                $query
                    ->whereNotNull('image_url')
                    ->orWhereNotNull('movie_url');
            })
            ->where(function ($query) use ($excludedChocoCasts, $excludedChocoGuests) {
                $query
                    ->whereNotNull('cast_id')
                    ->when(!empty($excludedChocoCasts), function ($query) use ($excludedChocoCasts) {
                        $query->whereNotIn('cast_id', $excludedChocoCasts);
                    })
                    ->orWhereNotNull('mypage_id')
                    ->orWhereNotNull('night_cast_id')
                    ->orWhereNotNull('guest_id')
                    ->when(!empty($excludedChocoGuests), function ($query) use($excludedChocoGuests) {
                        $query->whereNotIn('guest_id',  $excludedChocoGuests);
                    });
            });
    }

    protected function buildCastTissueQuery(
        Carbon $startDate,
        Carbon $endDate,
        array $excludedChocoCasts = []
    ) {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                'id',
                'cast_id',
                'night_cast_id',
                'good_count',
                'add_good_count',
                'view_count',
                'set_top_status',
                'release_date'
            )
            ->from((new Tissue)->getTable(), 'tissues')
            ->where('published_flg', DB::raw(Tissue::PUBLISHED_FLG_TRUE))
            ->where('tissue_status', DB::raw(Tissue::TISSUE_STATUS_NORMAL))
            ->whereBetween('release_date', [$startDate, $endDate])
            ->where(function ($query) use ($excludedChocoCasts) {
                $query
                    ->whereNotNull('cast_id')
                    ->when(!empty($excludedChocoCasts), function ($query) use ($excludedChocoCasts) {
                        $query->whereNotIn('cast_id', $excludedChocoCasts);
                    })
                    ->orWhereNotNull('night_cast_id');
            });
    }

    protected function buildShopTissueQuery(Carbon $startDate, Carbon $endDate)
    {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                'id',
                'cast_id',
                'night_cast_id'
            )
            ->from((new Tissue)->getTable(), 'tissues')
            ->where('published_flg', DB::raw(Tissue::PUBLISHED_FLG_TRUE))
            ->where('tissue_status', DB::raw(Tissue::TISSUE_STATUS_NORMAL))
            ->whereBetween('release_date', [$startDate, $endDate])
            ->where(function ($query) {
                $query
                    ->whereNotNull('cast_id')
                    ->orWhereNotNull('night_cast_id');
            });
    }

    protected function buildTissueCommentQuery()
    {
        $tissueCommentQuery = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->from((new TissueComment)->getTable(), 'tissue_comments')
            ->where('del', DB::raw(0));

        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select('tissue_comments.*')
            ->fromSub($tissueCommentQuery, 'tissue_comments')
            ->leftJoin(
                'tissue_comment AS master_tissue_comments',
                'master_tissue_comments.id',
                '=',
                'tissue_comments.master_comment_id'
            )
            ->whereNull('master_tissue_comments.id')
            ->where('master_tissue_comments.del', '=', DB::raw(0));
    }

    protected function buildTissueCommentLastOneQuery()
    {
        $tissueCommentQuery = $this->buildTissueCommentQuery();

        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                'tissue_id',
                DB::raw('MAX(created_at) AS created_at')
            )
            ->fromSub($tissueCommentQuery, 'tissue_comments')
            ->groupBy('tissue_id');
    }

    protected function buildHashtagQuery(array $displayedHashtagIds = [])
    {
        return DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                "hashtags.id AS id",
                "hashtags.name AS name",
                "hashtags.active_flg",
                "hashtags.event_type AS event_type",
                "hashtags.add_count AS add_count",
                'tissue_hashtags.tissue_id AS tissue_id'
            )
            ->from((new Hashtag)->getTable(), 'hashtags')
            ->rightJoin('tissue_hashtags', 'hashtags.id', '=', 'tissue_hashtags.hashtag_id')
            ->when(!empty($displayedHashtagIds), function ($query) use ($displayedHashtagIds) {
                $query->whereNotIn('id', $displayedHashtagIds);
            });
    }
}
