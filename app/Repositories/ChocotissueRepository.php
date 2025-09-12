<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use App\Models\Chocolat\Tissue;
use App\Models\Chocolat\MensTissue;
use App\Models\Chocolat\TissueRecommendPc;
use App\Models\Chocolat\TissueRecommendSp;
use App\Models\Chocolat\TissueNightOsusumeActiveView;
use App\Models\Chocolat\TissueActiveView;
use App\Models\Chocolat\WeeklyRankingPoint;
use App\Models\Chocolat\RankingPoint;
use App\Models\Chocolat\Guest;
use App\Models\Chocolat\ShopMain;
use App\Models\Chocolat\YoasobiShopsAll;
use App\Traits\Chocotissue\CommonQueries;
use App\Traits\Chocotissue\DateWindows;
use App\Traits\Chocotissue\ExcludedUsers;
use App\QueryBuilders\Chocotissue\UserScoreQueryBuilder;
use App\QueryBuilders\Chocotissue\UserTopTissueQueryBuilder;

class ChocotissueRepository
{
    use CommonQueries, DateWindows, ExcludedUsers;

    public function getTimeline(
        int $limit,
        int $offset = 0,
        int $prefId = null,
    ): \Illuminate\Support\Collection {
        $query = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->from((new TissueActiveView)->getTable())
            ->select(
                "tissue_id",
                "target_id",
                "tissue_type",
                "tissue_from_type",
                "pref_id",
                "release_date"
            )
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
        int $prefId = null,
    ): \Illuminate\Support\Collection {
        $startDate = $this->championshipStartDatetime();
        $endDate = $this->nowDatetime();

        $tissues = $this->buildOsusumeTissueQuery($startDate, $endDate);

        try {
            $query = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
                ->query()
                ->from((new TissueRecommendPc)->getTable())
                ->rightJoinSub($tissues, 'tissues', function ($join) {
                    $join->on(DB::raw("
                        CONCAT(
                            IF(tissue_recommend_pc.tissue_type = '" . Tissue::TISSUE_TYPE_GIRL . "', '1', '2'),
                            CAST(tissue_recommend_pc.tissue_id AS CHAR)
                        )
                    "), '=', 'tissues.id');
                })
                ->select(
                    "tissues.tissue_id",
                    "tissues.target_id",
                    "tissues.tissue_type",
                    "tissues.tissue_from_type"
                )
                ->when(isset($prefId) ,function ($query) use ($prefId) {
                    $query->where('tissue_recommend_pc.pref_id', $prefId);
                })
                ->orderBy("tissue_recommend_pc.id", 'desc')
                ->skip($offset)
                ->take($limit);

            return $query->get();
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('Database query failed', [
                'method' => 'getRecommendations',
                'error' => $e->getMessage(),
                'sql' => $e->getSql()
            ]);
            
            throw new \RuntimeException('資料庫查詢失敗');
        }
    }

    public function getSpRecommendations(
        int $limit,
        int $offset = 0,
        int $prefId = null,
    ): \Illuminate\Support\Collection {
        $startDate = $this->championshipStartDatetime();
        $endDate = $this->nowDatetime();

        $tissues = $this->buildOsusumeTissueQuery($startDate, $endDate);

        $query = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->from((new TissueRecommendSp)->getTable())
            ->rightJoinSub($tissues, 'tissues', function ($join) {
                $join->on(DB::raw("
                    CONCAT(
                        IF(tissue_recommend_sp.tissue_type = '" . Tissue::TISSUE_TYPE_GIRL . "', '1', '2'),
                        CAST(tissue_recommend_sp.tissue_id AS CHAR)
                    )
                "), '=', 'tissues.id');
            })
            ->select(
                "tissues.tissue_id",
                "tissues.target_id",
                "tissues.tissue_type",
                "tissues.tissue_from_type"
            )
            ->when(isset($prefId) ,function ($query) use ($prefId) {
                $query->where('tissue_recommend_sp.pref_id', $prefId);
            })
            ->orderBy("tissue_recommend_sp.id", 'desc')
            ->skip($offset)
            ->take($limit);

        return $query->get();
    }

    public function getUserWeeklyRankings(
        int $limit,
        int $offset = 0,
        int $prefId = null,
    ): \Illuminate\Support\Collection {
        $startDate = $this->championshipStartDatetime();
        $endDate = $this->nowDatetime();
        $weekStartDate = $this->weekStartDate();
        $lastWeekStartDate = $this->lastWeekStartDate();

        $weeklyRankingPointQuery = $this->buildWeeklyRankingPointQuery($lastWeekStartDate);
        $chocoMypageQuery = $this->buildChocoMypageQuery();
        $chocoGuestQuery = $this->buildChocoGuestQuery();
        $chocoShopQuery = $this->buildChocoShopQuery();
        $nightShopQuery = $this->buildNightShopQuery();
        $tissueQuery = $this->buildUserTissueQuery($startDate, $endDate);

        $userScoreQuery = (new UserScoreQueryBuilder)->build(
            $tissueQuery,
            $weeklyRankingPointQuery,
            $chocoMypageQuery,
            $chocoGuestQuery,
            $weekStartDate
        );

        $query = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->fromSub($userScoreQuery, 'user_scores')
            ->leftJoinSub($chocoShopQuery, 'choco_shops', 'choco_shops.id', '=', 'user_scores.choco_shop_table_id')
            ->leftJoinSub($nightShopQuery, 'night_shops', 'night_shops.id', '=', 'user_scores.night_shop_table_id')
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
                "user_scores.tissue_count",
                "user_scores.last_tissue_id",
            )
            ->when(isset($prefId) ,function ($query) use ($prefId) {
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
        int $prefId = null,
    ): \Illuminate\Support\Collection {
        $startDate = $this->championshipStartDateTime();
        $endDate = $this->nowDatetime();

        $rankingPointQuery = $this->buildRankingPointQuery($startDate);
        $chocoMypageQuery = $this->buildChocoMypageQuery();
        $chocoGuestQuery = $this->buildChocoGuestQuery();
        $chocoShopQuery = $this->buildChocoShopQuery();
        $nightShopQuery = $this->buildNightShopQuery();
        $tissueQuery = $this->buildUserTissueQuery($startDate, $endDate);

        $userScoreQuery = (new UserScoreQueryBuilder)->build(
            $tissueQuery,
            $rankingPointQuery,
            $chocoMypageQuery,
            $chocoGuestQuery,
            $startDate
        );

        $query = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->fromSub($userScoreQuery, 'user_scores')
            ->leftJoinSub($chocoShopQuery, 'choco_shops', 'choco_shops.id', '=', 'user_scores.choco_shop_table_id')
            ->leftJoinSub($nightShopQuery, 'night_shops', 'night_shops.id', '=', 'user_scores.night_shop_table_id')
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
                "user_scores.last_tissue_id",
            )
            ->when(isset($prefId) ,function ($query) use ($prefId) {
                $query
                    ->where('choco_shops.pref_id', $prefId)
                    ->orWhere('night_shops.pref_id', $prefId);
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
        $startDate = $this->championshipStartDateTime();
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
            ->fromSub($uniqueCastShopPointQuery, 'unique_cast_shop_points')
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

    public function getTissues(array $ids): \Illuminate\Database\Eloquent\Collection
    {
        $query = Tissue::query()
            ->whereIn('id', $ids);

        return $query->get();
    }

    public function getMensTissues(array $ids): \Illuminate\Database\Eloquent\Collection
    {
        $query = MensTissue::query()
            ->whereIn('id', $ids);

        return $query->get();
    }

    public function getTopTissueOfUsers(
        array $chocoCastIds,
        array $nightCastIds,
        array $chocoMypageIds,
        array $chocoGuestIds
    ): \Illuminate\Database\Eloquent\Collection {
        $startDate = $this->championshipStartDateTime();
        $endDate = $this->nowDatetime();

        $tissueQuery = $this->buildUserTissueQuery($startDate, $endDate);
        $chocoMypageQuery = $this->buildChocoMypageQuery();
        $chocoGuestQuery = $this->buildChocoGuestQuery();

        $build = new UserTopTissueQueryBuilder;
        $userTissueQuery = $build->buildUserTissueQuery($tissueQuery, $chocoMypageQuery, $chocoGuestQuery);
        $orderTissues = $build->buildOrderTissueQuery($userTissueQuery);

        $query = Tissue::query()
            ->fromSub($orderTissues, 'tissues')
            ->select('*')
            ->where('tissues.show_num', DB::raw(1))
            ->where(function ($query) use ($chocoCastIds, $nightCastIds, $chocoMypageIds, $chocoGuestIds) {
                $query
                    ->when(!empty($chocoCastIds), function ($query) use ($chocoCastIds) {
                        $query->whereIn('tissues.user_id_for_choco_cast', $chocoCastIds);
                    })
                    ->when(!empty($nightCastIds), function ($query) use ($nightCastIds) {
                        $query->orWhereIn('tissues.night_cast_id', $nightCastIds);
                    })
                    ->when(!empty($chocoMypageIds), function ($query) use ($chocoMypageIds) {
                        $query->orWhereIn('tissues.mypage_id', $chocoMypageIds);
                    })
                    ->when(!empty($chocoGuestIds), function ($query) use ($chocoGuestIds) {
                        $query->orWhereIn('tissues.guest_id', $chocoGuestIds);
                    });
            });

        return $query->get();
    }
}
