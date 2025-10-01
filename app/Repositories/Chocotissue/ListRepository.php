<?php

namespace App\Repositories\Chocotissue;

use Illuminate\Support\Facades\DB;
use App\Models\Chocolat\Tissue;
use App\Models\Chocolat\TissueNightRecommendPc;
use App\Models\Chocolat\TissueNightRecommendSp;
use App\Models\Chocolat\TissueActiveView;
use App\QueryBuilders\Chocotissue\UserScoreQueryBuilder;
use App\QueryBuilders\Chocotissue\ShopRankingQueryBuilder;
use App\QueryBuilders\Chocotissue\HashtagQueryBuilder;
use App\Traits\Chocotissue\CommonQueries;
use App\Traits\Chocotissue\DateWindows;
use App\Traits\Chocotissue\ExcludedUsers;

class ListRepository
{
    use CommonQueries;
    use DateWindows;
    use ExcludedUsers;

    public function getTimeline(
        int $limit,
        int $offset = 0,
        ?int $prefId = null
    ): \Illuminate\Support\Collection {
        $query = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                "tissue_id",
                "target_id",
                "tissue_type",
                "tissue_from_type",
                "pref_id",
                "release_date"
            )
            ->from((new TissueActiveView)->getTable())
            ->when(isset($prefId) ,function ($query) use ($prefId) {
                $query->where('pref_id', $prefId);
            })
            ->orderBy("release_date", 'desc')
            ->orderBy("id", 'desc')
            ->skip($offset)
            ->take($limit);

        return $query->get();
    }

    public function getPcRecommendations(
        int $limit,
        int $offset = 0,
        ?int $prefId = null
    ): \Illuminate\Support\Collection {
        $startDate = $this->lastChampionshipStartDatetime();
        $endDate = $this->nowDatetime();

        $tissues = $this->buildOsusumeTissueQuery($startDate, $endDate);

        try {
            $query = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
                ->query()
                ->select(
                    "tissues.tissue_id",
                    "tissues.target_id",
                    "tissues.tissue_type",
                    "tissues.tissue_from_type"
                )
                ->fromSub($tissues, 'tissues')
                ->rightJoin((new TissueNightRecommendPc)->getTable(), function ($join) {
                    $join
                        ->on('tissue_night_recommend_pc.tissue_id', '=', 'tissues.tissue_id')
                        ->on('tissue_night_recommend_pc.tissue_type', '=', 'tissues.tissue_type');
                })
                ->when(isset($prefId) && !empty($prefId) ,function ($query) use ($prefId) {
                    $query->where('tissue_night_recommend_pc.pref_id', $prefId);
                })
                ->orderBy("tissue_night_recommend_pc.id", 'ASC')
                ->skip($offset)
                ->take($limit);

            return $query->get();
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('Database query failed', [
                'method' => 'getRecommendations',
                'error' => $e->getMessage(),
                'sql' => $e->getSql(),
            ]);

            throw new \RuntimeException('資料庫查詢失敗');
        }
    }

    public function getSpRecommendations(
        int $limit,
        int $offset = 0,
        ?int $prefId = null
    ): \Illuminate\Support\Collection {
        $startDate = $this->lastChampionshipStartDatetime();
        $endDate = $this->nowDatetime();

        $tissues = $this->buildOsusumeTissueQuery($startDate, $endDate);

        $query = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                "tissues.tissue_id",
                "tissues.target_id",
                "tissues.tissue_type",
                "tissues.tissue_from_type"
            )
            ->fromSub($tissues, 'tissues')
            ->rightJoin((new TissueNightRecommendSp)->getTable(), function ($join) {
                $join
                    ->on('tissue_night_recommend_sp.tissue_id', '=', 'tissues.tissue_id')
                    ->on('tissue_night_recommend_sp.tissue_type', '=', 'tissues.tissue_type');
            })
            ->when(isset($prefId) && !empty($prefId), function ($query) use ($prefId) {
                $query->where('tissue_night_recommend_sp.pref_id', $prefId);
            })
            ->orderBy("tissue_night_recommend_sp.id", 'ASC')
            ->skip($offset)
            ->take($limit);

        return $query->get();
    }

    public function getUserWeeklyRankings(
        int $limit,
        int $offset = 0,
        ?int $prefId = null
    ): \Illuminate\Support\Collection {
        $startDate = $this->championshipStartDatetime();
        $endDate = $this->nowDatetime();
        $lastWeekStartDate = $this->lastWeekStartDate();
        $weekStartDate = $this->weekStartDate();
        $snsWeekStartDate = $this->snsWeekStartDate();

        $tissueQuery = $this->buildUserTissueQuery(
            $startDate,
            $endDate,
            $this->excludedChocoCasts(),
            $this->excludedChocoGuests()
        );
        $weeklyRankingPointQuery = $this->buildWeeklyRankingPointQuery($lastWeekStartDate);
        $chocoMypageQuery = $this->buildChocoMypageQuery();
        $chocoGuestQuery = $this->buildChocoGuestQuery();
        $chocoShopQuery = $this->buildChocoShopQuery();
        $nightShopQuery = $this->buildNightShopQuery();
        $tissueCommentQuery = $this->buildTissueCommentQuery();

        $tissueQuery = (new UserScoreQueryBuilder)->buildTissueQueryBuild(
            $tissueQuery,
            $tissueCommentQuery
        );
        $userScoreQuery = (new UserScoreQueryBuilder)->buildWeeklyScoreQueryBuild(
            $tissueQuery,
            $weeklyRankingPointQuery,
            $chocoMypageQuery,
            $chocoGuestQuery,
            $weekStartDate,
            $snsWeekStartDate
        );

        $query = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                "user_scores.choco_cast_id",
                "choco_shops.id AS choco_shop_table_id",
                "choco_shops.pref_id AS choco_shop_pref_id",
                "user_scores.night_cast_id",
                "night_shops.id AS night_shop_table_id",
                "night_shops.pref_id AS night_shop_pref_id",
                "user_scores.choco_mypage_id",
                "user_scores.choco_guest_id",
                "user_scores.point",
                "user_scores.total_good_count",
                "user_scores.total_sns_count",
                "user_scores.total_comment_count",
                "user_scores.tissue_count",
                "user_scores.last_tissue_id"
            )
            ->fromSub($userScoreQuery, 'user_scores')
            ->leftJoinSub($chocoShopQuery, 'choco_shops', 'choco_shops.id', '=', 'user_scores.choco_shop_table_id')
            ->leftJoinSub($nightShopQuery, 'night_shops', 'night_shops.id', '=', 'user_scores.night_shop_table_id')
            ->when(isset($prefId) && !empty($prefId), function ($query) use ($prefId) {
                $query
                    ->where('choco_shops.pref_id', $prefId)
                    ->orWhere('night_shops.pref_id', $prefId);
            })
            ->orderBy('user_scores.total_good_count', 'DESC')
            ->orderBy('user_scores.tissue_count', 'DESC')
            ->orderBy('user_scores.last_tissue_id', 'DESC')
            ->skip($offset)
            ->take($limit);

        return $query->get();
    }

    public function getUserRankings(
        int $limit,
        int $offset = 0,
        ?int $prefId = null
    ): \Illuminate\Support\Collection {
        $startDate = $this->championshipStartDatetime();
        $endDate = $this->nowDatetime();

        $tissueQuery = $this->buildUserTissueQuery(
            $startDate,
            $endDate,
            $this->excludedChocoCasts(),
            $this->excludedChocoGuests()
        );
        $rankingPointQuery = $this->buildRankingPointQuery($startDate);
        $chocoMypageQuery = $this->buildChocoMypageQuery();
        $chocoGuestQuery = $this->buildChocoGuestQuery();
        $chocoShopQuery = $this->buildChocoShopQuery();
        $nightShopQuery = $this->buildNightShopQuery();
        $tissueCommentQuery = $this->buildTissueCommentQuery();

        $tissueQuery = (new UserScoreQueryBuilder)->buildTissueQueryBuild(
            $tissueQuery,
            $tissueCommentQuery
        );
        $userScoreQuery = (new UserScoreQueryBuilder)->buildScoreQueryBuild(
            $tissueQuery,
            $rankingPointQuery,
            $chocoMypageQuery,
            $chocoGuestQuery
        );

        $query = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                "user_scores.choco_cast_id",
                "choco_shops.id AS choco_shop_table_id",
                "choco_shops.pref_id AS choco_shop_pref_id",
                "user_scores.night_cast_id",
                "night_shops.id AS night_shop_table_id",
                "night_shops.pref_id AS night_shop_pref_id",
                "user_scores.choco_mypage_id",
                "user_scores.choco_guest_id",
                "user_scores.point",
                "user_scores.total_good_count",
                "user_scores.total_sns_count",
                "user_scores.total_comment_count",
                "user_scores.last_tissue_id"
            )
            ->fromSub($userScoreQuery, 'user_scores')
            ->leftJoinSub($chocoShopQuery, 'choco_shops', 'choco_shops.id', '=', 'user_scores.choco_shop_table_id')
            ->leftJoinSub($nightShopQuery, 'night_shops', 'night_shops.id', '=', 'user_scores.night_shop_table_id')
            ->when(isset($prefId) && !empty($prefId), function ($query) use ($prefId) {
                $query->where(function ($query) use ($prefId) {
                    $query
                        ->where('choco_shops.pref_id', $prefId)
                        ->orWhere('night_shops.pref_id', $prefId);
                });
            })
            ->orderBy('user_scores.point', 'DESC')
            ->orderBy('user_scores.last_tissue_id', 'DESC')
            ->skip($offset)
            ->take($limit);

        return $query->get();
    }

    public function getShopRankings(
        array $displayedChocoShopTableIds,
        array $displayedNightShopTableIds,
        int $limit = 0
    ): \Illuminate\Support\Collection {
        $startDate = $this->championshipStartDatetime();
        $endDate = $this->nowDatetime();

        $tissueQuery = $this->buildShopTissueQuery($startDate, $endDate);
        $rankingPointQuery = $this->buildRankingPointQuery($startDate);

        $builder = new ShopRankingQueryBuilder;
        $eligibleTissueQuery = $builder->buildEligibleTissue(
            $tissueQuery,
            $rankingPointQuery
        );
        $tissueChocoShopQuery = $builder->buildTissueChocoShop($eligibleTissueQuery);
        $tissueNightShopQuery = $builder->buildTissueNightShop($eligibleTissueQuery);
        $unpivotedShopQuery = $builder->buildUnpivoted($tissueChocoShopQuery, $tissueNightShopQuery);
        $uniqueCastShopPointQuery = $builder->buildUniqueCastShopPoints($unpivotedShopQuery);

        $query = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
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
            ->when(!empty($displayedChocoShopTableIds), function ($query) use ($displayedChocoShopTableIds) {
                $query->whereNotIn('choco_shop_table_id', $displayedChocoShopTableIds);
            })
            ->when(!empty($displayedNightShopTableIds), function ($query) use ($displayedNightShopTableIds) {
                $query->whereNotIn('night_shop_table_id', $displayedNightShopTableIds);
            })
            ->groupBy('canonical_shop_id')
            ->orderBy('rank_point', 'DESC')
            ->orderBy('tissue_count', 'DESC')
            ->orderBy('choco_shop_table_id', 'ASC')
            ->orderBy('night_shop_table_id', 'ASC')
            ->when($limit > 0, function ($query) use ($limit) {
                $query->take($limit);
            });

        return $query->get();
    }

    public function getShopRankingDetailTimeline(
        ?array $chocoShopTableIds = null,
        ?array $nightShopTableIds = null,
        ?int $limit = null,
        int $offset = 0
    ): \Illuminate\Support\Collection {
        $startDate = $this->championshipStartDatetime();
        $endDate = $this->nowDatetime();

        $tissueQuery = $this->buildCastTissueQuery($startDate, $endDate, $this->excludedChocoCasts());
        $tissueCommentLastOneQuery = $this->buildTissueCommentLastOneQuery();
        $tissueType = Tissue::TISSUE_TYPE_GIRL;

        $query = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                "tissues.id AS tissue_id",
                "tissues.release_date",
                "last_tissue_comments.created_at AS last_comment_datetime",
                DB::raw("'{$tissueType}' AS tissue_type")
            )
            ->fromSub($tissueQuery, 'tissues')
            ->leftJoin('casts AS choco_casts', 'choco_casts.id', '=', 'tissues.cast_id')
            ->leftJoin('yoasobi_casts AS night_casts', 'night_casts.id', '=', 'tissues.night_cast_id')
            ->leftJoinSub(
                $tissueCommentLastOneQuery,
                'last_tissue_comments',
                'last_tissue_comments.tissue_id',
                '=',
                'tissues.id'
            )
            ->when(!empty($chocoShopTableIds), function ($query) use ($chocoShopTableIds) {
                $query->whereIn("choco_casts.shop_table_id", $chocoShopTableIds);
            })
            ->when(!empty($nightShopTableIds), function ($query) use ($nightShopTableIds) {
                $query->orWhereIn("night_casts.shop_id", $nightShopTableIds);
            })
            ->orderBy(DB::raw("(
                CASE
                    WHEN last_tissue_comments.created_at IS NOT NULL AND last_tissue_comments.created_at > tissues.release_date THEN
                        last_tissue_comments.created_at
                    ELSE
                        tissues.release_date
                END
            )"), "DESC")
            ->orderBy("tissue_id", 'DESC')
            ->skip($offset)
            ->when(!empty($limit), function ($query) use ($limit, $offset) {
                $query
                    ->skip($offset)
                    ->take($limit);
            });

        return $query->get();
    }

    public function getShopRankingDetailRanking(
        ?array $chocoShopTableIds = null,
        ?array $nightShopTableIds = null,
        ?int $limit = null,
        int $offset = 0
    ): \Illuminate\Support\Collection {
        $startDate = $this->championshipStartDatetime();
        $endDate = $this->nowDatetime();

        $tissueQuery = $this->buildCastTissueQuery($startDate, $endDate, $this->excludedChocoCasts());
        $rankingPointQuery = $this->buildRankingPointQuery($startDate);

        $build = new UserScoreQueryBuilder;
        $castScoreQuery = $build->castQueryBuild($tissueQuery, $rankingPointQuery);

        $query = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                "choco_cast_id",
                "night_cast_id",
                "choco_shop_table_id",
                "night_shop_table_id",
                "rank_point",
                "tissue_count",
                "last_tissue_id"
            )
            ->fromSub($castScoreQuery, 'cast_scores')
            ->when(!empty($chocoShopTableIds), function ($query) use ($chocoShopTableIds) {
                $query->whereIn("choco_shop_table_id", $chocoShopTableIds);
            })
            ->when(!empty($nightShopTableIds), function ($query) use ($nightShopTableIds) {
                $query->orWhereIn("night_shop_table_id", $nightShopTableIds);
            })
            ->orderBy("rank_point", 'DESC')
            ->orderBy("last_tissue_id", 'DESC')
            ->when(!empty($limit), function ($query) use ($limit, $offset) {
                $query
                    ->skip($offset)
                    ->take($limit);
            });

        return $query->get();
    }

    public function getHashtags(
        array $displayedHashtagIds,
        ?int $limit = null
    ): \Illuminate\Support\Collection {
        $startDate = $this->championshipStartDatetime();
        $endDate = $this->nowDatetime();

        $tissueQuery = $this->buildUserTissueQuery(
            $startDate,
            $endDate,
            $this->excludedChocoCasts(),
            $this->excludedChocoGuests()
        );
        $chocoMypageQuery = $this->buildChocoMypageQuery();
        $chocoGuestQuery = $this->buildChocoGuestQuery();
        $tissueCommentLastOneQuery = $this->buildTissueCommentLastOneQuery();
        $hashtagQuery = $this->buildHashtagQuery($displayedHashtagIds);

        $builder = new HashtagQueryBuilder;
        $eligibleTissueQuery = $builder->buildEligibleTissue(
            $tissueQuery,
            $chocoMypageQuery,
            $chocoGuestQuery,
            $tissueCommentLastOneQuery
        );
        $tissueHashtagQuery = $builder->buildTissueHashtag($hashtagQuery, $eligibleTissueQuery);
        $tissueHashtagShowNumQuery = $builder->buildTissueHashtagShowNum($tissueHashtagQuery);
        $rankingQuery = $builder->buildRanking($tissueHashtagShowNumQuery);

        $query = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                "ranking_data.hashtag_id AS hashtag_id",
                DB::raw("MIN(order_num) AS order_num"),
                DB::raw("SUM(view_count) + ANY_VALUE(add_count) AS total_view_count"),
                DB::raw("ANY_VALUE(last_update_datetime) AS last_update_datetime")
            )
            ->fromSub($rankingQuery, 'ranking_data')
            ->groupBy('hashtag_id')
            ->orderBy("order_num", 'ASC')
            ->when(!empty($limit), function ($query) use ($limit) {
                $query->take($limit);
            });

        return $query->get();
    }

    public function getHashtagDetailTimeline(
        ?int $hashtagId = null,
        ?int $limit = null,
        int $offset = 0
    ): \Illuminate\Support\Collection {
        $startDate = $this->championshipStartDatetime();
        $endDate = $this->nowDatetime();

        $tissueQuery = $this->buildUserTissueQuery(
            $startDate,
            $endDate,
            $this->excludedChocoCasts(),
            $this->excludedChocoGuests()
        );
        $chocoMypageQuery = $this->buildChocoMypageQuery();
        $chocoGuestQuery = $this->buildChocoGuestQuery();
        $tissueCommentLastOneQuery = $this->buildTissueCommentLastOneQuery();
        $tissueType = Tissue::TISSUE_TYPE_GIRL;

        $builder = new HashtagQueryBuilder;
        $eligibleTissueQuery = $builder->buildEligibleTissue(
            $tissueQuery,
            $chocoMypageQuery,
            $chocoGuestQuery,
            $tissueCommentLastOneQuery
        );

        $baseQuery = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                DB::raw("(
                    CASE
                        WHEN last_comment_datetime IS NOT NULL AND last_comment_datetime > tissues.release_date THEN
                            last_comment_datetime
                        ELSE
                            tissues.release_date
                    END
                ) AS last_update_datetime"),
                "tissues.id AS tissue_id",
                "hashtags.hashtag_id",
            )
            ->from('tissue_hashtags', 'hashtags')
            ->rightJoinSub($eligibleTissueQuery, 'tissues', 'hashtags.tissue_id', '=', 'tissues.id');

        $query = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->from($baseQuery, 'base_data')
            ->select(
                "*",
                DB::raw("'{$tissueType}' AS tissue_type")
            )
            ->when(!empty($hashtagId), function ($query) use ($hashtagId) {
                $query->where("hashtag_id", '=', DB::raw($hashtagId));
            })
            ->orderBy("last_update_datetime", "DESC")
            ->orderBy("tissue_id", 'DESC')
            ->when(!empty($limit), function ($query) use ($limit, $offset) {
                $query
                    ->skip($offset)
                    ->take($limit);
            });

        return $query->get();
    }

    public function getHashtagDetailRanking(
        ?int $hashtagId = null,
        ?int $limit = null,
        int $offset = 0
    ): \Illuminate\Support\Collection {
        $startDate = $this->championshipStartDatetime();
        $endDate = $this->nowDatetime();

        $tissueQuery = $this->buildUserTissueQuery(
            $startDate,
            $endDate,
            $this->excludedChocoCasts(),
            $this->excludedChocoGuests()
        );
        $chocoMypageQuery = $this->buildChocoMypageQuery();
        $chocoGuestQuery = $this->buildChocoGuestQuery();

        $eligibleTissueQuery = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                "tissues.*"
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
            ->whereNotNull('choco_casts.id')
            ->orWhereNotNull('night_casts.id')
            ->orWhereNotNull('choco_guests.id')
            ->orWhereNotNull('choco_mypages.id');

        $baseQuery = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select(
                "hashtags.hashtag_id AS hashtag_id",
                "tissues.cast_id AS choco_cast_id",
                "tissues.night_cast_id AS night_cast_id",
                "tissues.mypage_id AS choco_mypage_id",
                "tissues.guest_id AS choco_guest_id",
                "tissues.good_count AS good_count",
                "tissues.add_good_count AS add_good_count",
                "tissues.view_count AS view_count"
            )
            ->from('tissue_hashtags', 'hashtags')
            ->rightJoinSub($eligibleTissueQuery, 'tissues', function ($join) {
                $join->on('hashtags.tissue_id', '=', 'tissues.id');
            })
            ->when(!empty($hashtagId), function ($query) use ($hashtagId) {
                $query->where('hashtags.hashtag_id', '=', $hashtagId);
            });

        $query = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->fromSub($baseQuery, 'base_data')
            ->select(
                "choco_cast_id",
                "night_cast_id",
                "choco_mypage_id",
                "choco_guest_id",
                DB::raw("SUM(IFNULL(add_good_count, 0) + IFNULL(good_count, 0)) AS good_count"),
                DB::raw("SUM(IFNULL(view_count, 0)) AS view_count")
            )
            ->groupBy('choco_cast_id', 'night_cast_id', 'choco_mypage_id', 'choco_guest_id')
            ->orderBy("good_count", 'DESC')
            ->orderBy("view_count", 'DESC')
            ->when(!empty($limit), function ($query) use ($limit) {
                $query->take($limit);
            });

        return $query->get();
    }

    public function getTissues(
        array $tissueIds,
        int $limit,
        int $offset = 0
    ): \Illuminate\Support\Collection {
        $startDate = $this->championshipStartDatetime();
        $endDate = $this->nowDatetime();

        $tissueQuery = $this->buildUserTissueQuery(
            $startDate,
            $endDate,
            $this->excludedChocoCasts(),
            $this->excludedChocoGuests()
        );
        $chocoMypageQuery = $this->buildChocoMypageQuery();
        $chocoGuestQuery = $this->buildChocoGuestQuery();
        $tissueType = Tissue::TISSUE_TYPE_GIRL;

        $tissueQuery = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->select("tissues.*")
            ->fromSub($tissueQuery, 'tissues')
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

        $query = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->from($tissueQuery, 'tissues')
            ->select(
                "tissues.id AS tissue_id",
                "tissues.release_date",
                DB::raw("'{$tissueType}' AS tissue_type")
            )
            ->when(!empty($tissueIds), function ($query) use ($tissueIds) {
                $query->whereIn("id", $tissueIds);
            })
            ->orderBy("release_date", 'DESC')
            ->orderBy("id", 'ASC')
            ->skip($offset)
            ->take($limit);

        return $query->get();
    }
}
