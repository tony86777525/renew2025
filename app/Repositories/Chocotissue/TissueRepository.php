<?php

namespace App\Repositories\Chocotissue;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Chocolat\Tissue;
use App\Models\Chocolat\MensTissue;
use App\QueryBuilders\Chocotissue\UserTopTissueQueryBuilder;
use App\Traits\Chocotissue\CommonQueries;
use App\Traits\Chocotissue\DateWindows;
use App\Traits\Chocotissue\ExcludedUsers;

class TissueRepository
{
    use CommonQueries;
    use DateWindows;
    use ExcludedUsers;

    private UserTopTissueQueryBuilder $userTopTissueQueryBuilder;

    public function __construct(
        UserTopTissueQueryBuilder $userTopTissueQueryBuilder
    ) {
        $this->userTopTissueQueryBuilder = $userTopTissueQueryBuilder;
    }

    /**
     * Get tissues.
     *
     * @param array $ids
     * @return Collection
     */
    public function getTissues(array $ids): Collection
    {
        $query = Tissue::query()
            ->whereIn('id', $ids);

        return $query->get();
    }

    /**
     * Get mens tissues.
     *
     * @param array $ids
     * @return Collection
     */
    public function getMensTissues(array $ids): Collection
    {
        $query = MensTissue::query()
            ->whereIn('id', $ids);

        return $query->get();
    }

    /**
     * Get user ranking top tissue of users.
     *
     * @param array $chocoCastIds
     * @param array $nightCastIds
     * @param array $chocoMypageIds
     * @param array $chocoGuestIds
     * @return Collection
     */
    public function getUserRankingTopTissueOfUsers(
        array $chocoCastIds,
        array $nightCastIds,
        array $chocoMypageIds,
        array $chocoGuestIds
    ): Collection {
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

        $userTissueQuery = $this->userTopTissueQueryBuilder->buildUserTissueQuery(
            $tissueQuery,
            $chocoMypageQuery,
            $chocoGuestQuery
        );
        $orderTissues = $this->userTopTissueQueryBuilder->buildUserRankingOrderTissueQuery($userTissueQuery);

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

    /**
     * Get shop ranking top tissue of users.
     *
     * @param array $chocoShopTableIds
     * @param array $nightShopTableIds
     * @return Collection
     */
    public function getShopRankingTopTissueOfUsers(
        array $chocoShopTableIds,
        array $nightShopTableIds
    ): Collection {
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

        $userTissueQuery = $this->userTopTissueQueryBuilder->buildUserTissueQuery(
            $tissueQuery,
            $chocoMypageQuery,
            $chocoGuestQuery
        );
        $orderTissues = $this->userTopTissueQueryBuilder->buildShopRankingOrderTissueQuery(
            $userTissueQuery,
            $tissueCommentLastOneQuery
        );

        $orderTissues
            ->where(function ($query) use ($chocoShopTableIds, $nightShopTableIds) {
                $query
                    ->when(!empty($chocoShopTableIds), function ($query) use ($chocoShopTableIds) {
                        $query->whereIn('tissues.choco_shop_table_id', $chocoShopTableIds);
                    })
                    ->when(!empty($nightShopTableIds), function ($query) use ($nightShopTableIds) {
                        $query->orWhereIn('tissues.night_shop_table_id', $nightShopTableIds);
                    });
            });

        $query = Tissue::query()
            ->fromSub($orderTissues, 'tissues')
            ->select('*')
            ->where('tissues.show_num', DB::raw(1));

        return $query->get();
    }

    /**
     * Get hashtag top tissue of users.
     *
     * @param array $hashtagIds
     * @return Collection
     */
    public function getHashtagTopTissueOfUsers(
        array $hashtagIds
    ): Collection {
        /**
         * 為每個指定的 hashtag，找出最多 10 篇具代表性的投稿。
         *
         * 何謂「代表性投稿」？
         * 在同一個 hashtag 下，每個「獨立用戶」所發布的「最新」一篇投稿，即為該用戶在此 hashtag 下的代表性投稿。
         *
         * 實現邏輯 (LOGIC): 這是一個複雜的「組內取 Top N」問題，透過兩階段的窗函數 (Window Functions) 來解決。
         *
         * 階段一 (內部查詢 - `tissueShowNumQuery`): 找出每個用戶在每個 hashtag 下的最新一篇投稿。
         *  - `PARTITION BY hashtag_id, mypage_id, cast_id, ...`: 將投稿依照「Hashtag」和「用戶ID」進行分組。
         *  - `ORDER BY last_update_datetime DESC`: 在每個分組內，依照投稿的最後更新時間降序排列。
         *  - `ROW_NUMBER() as show_num`: 為組內的每筆資料賦予排名。`show_num = 1` 的就是該用戶在此 hashtag 下的最新投稿。
         *
         * 階段二 (外部查詢 - `$query`): 從階段一的結果中，為每個 hashtag 選出前 10 篇代表性投稿。
         *  - `PARTITION BY hashtag_id`: 將所有用戶的「最新投稿」(`show_num = 1`) 依照 hashtag 進行分組。
         *  - `ORDER BY last_update_datetime DESC`: 在每個 hashtag 分組內，將這些代表性投稿依照時間降序排列。
         *  - `ROW_NUMBER() as order_number`: 為每個 hashtag 內的代表性投稿賦予最終排名。
         *  - `WHERE order_number <= 10`: 最終選出每個 hashtag 的前 10 名。
         */
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

        $tissueQuery = DB::connection('mysql-chocolat')
            ->query()
            ->fromSub($tissueQuery, 'tissues')
            ->select(
                'tissues.*',
                DB::raw("
                    (
                        CASE
                            WHEN last_tissue_comments.created_at IS NOT NULL THEN
                                last_tissue_comments.created_at
                            ELSE
                                tissues.release_date
                        END
                    ) AS last_update_datetime
                ")
            )
            ->leftJoinSub(
                $tissueCommentLastOneQuery,
                'last_tissue_comments',
                'last_tissue_comments.tissue_id',
                '=',
                'tissues.id'
            );

        $tissueShowNumQuery = DB::connection('mysql-chocolat')
            ->query()
            ->fromSub(function ($query) use ($tissueQuery, $chocoMypageQuery, $chocoGuestQuery, $hashtagIds) {
                $query
                    ->from('tissue_hashtags', 'hashtags')
                    ->rightJoinSub($tissueQuery, 'tissues', function ($join) {
                        $join->on('hashtags.tissue_id', '=', 'tissues.id');
                    })
                    ->leftJoin('casts AS choco_casts', 'choco_casts.id', '=', 'tissues.cast_id')
                    ->leftJoin('yoasobi_casts AS night_casts', 'night_casts.id', '=', 'tissues.night_cast_id')
                    ->leftJoinSub($chocoMypageQuery, 'choco_mypages', 'choco_mypages.id', '=', 'tissues.mypage_id')
                    ->leftJoinSub($chocoGuestQuery, 'choco_guests', 'choco_guests.id', '=', 'tissues.guest_id')
                    ->select(
                        'tissues.*',
                        'hashtags.hashtag_id',
                        DB::raw("
                            ROW_NUMBER() OVER (
                                PARTITION BY
                                    hashtags.hashtag_id,
                                    tissues.mypage_id,
                                    tissues.cast_id,
                                    tissues.night_cast_id,
                                    tissues.guest_id
                                ORDER BY
                                    tissues.last_update_datetime DESC,
                                    tissues.id ASC
                            ) as show_num
                        ")
                    )
                    ->where(function ($query) {
                        $query
                            ->whereNotNull('choco_casts.id')
                            ->orWhereNotNull('night_casts.id')
                            ->orWhereNotNull('choco_guests.id')
                            ->orWhereNotNull('choco_mypages.id');
                    })
                    ->when(!empty($hashtagIds), function ($query) use ($hashtagIds) {
                        $query->whereIn('hashtags.hashtag_id', $hashtagIds);
                    });
                }, 'tissues')
            ->select(
                'tissues.*',
                DB::raw("
                    ROW_NUMBER() OVER (
                        PARTITION BY hashtag_id
                        ORDER BY
                            last_update_datetime DESC,
                            id ASC
                    ) as order_number
                ")
            )
            ->where('show_num', '=', DB::raw(1));

        $query = Tissue::query()
            ->fromSub($tissueShowNumQuery, 'tissues')
            ->select('*')
            ->where('show_num', '=', DB::raw(1))
            ->where('order_number', '<=', DB::raw(10));

        return $query->get();
    }

    /**
     * Get hashtag detail rank top tissue of users.
     *
     * @param integer $hashtagId
     * @param array $chocoCastIds
     * @param array $nightCastIds
     * @param array $chocoMypageIds
     * @param array $chocoGuestIds
     * @return Collection
     */
    public function getHashtagDetailRankTopTissueOfUsers(
        int $hashtagId,
        array $chocoCastIds,
        array $nightCastIds,
        array $chocoMypageIds,
        array $chocoGuestIds
    ): Collection {
        /**
         * 獲取指定 Hashtag 下，各用戶評分最高的代表性投稿。
         *
         * 此方法用於 Hashtag 詳情頁的「排名」模式，為每個參與排名的用戶（不論是 cast, mypage 還是 guest）
         * 找出他們在該 Hashtag 下評分最高的一篇投稿。
         *
         * 實現邏輯 (LOGIC): 這是一個「組內取 Top 1」問題，透過 SQL 窗函數 (Window Functions) 來高效解決。
         *
         * - `PARTITION BY ..., tissues.mypage_id, tissues.cast_id, ...`: 將投稿依照「用戶ID」進行分組。
         * - `ORDER BY (讚數 + 加權讚數) DESC, 觀看數 DESC`: 在每個用戶的分組內，依照評分標準（讚數優先，其次觀看數）排序。
         * - `ROW_NUMBER() as show_num`: 為組內的每筆資料賦予排名。`show_num = 1` 的就是該用戶在此 hashtag 下評分最高的投稿。
         * - 外部查詢的 `WHERE show_num = 1`: 最終只選出每個用戶評分最高的那一篇投稿。
         */
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

        $tissueShowNumQuery = DB::connection('mysql-chocolat')
            ->query()
            ->from('tissue_hashtags', 'hashtags')
            ->rightJoinSub($tissueQuery, 'tissues', function ($join) {
                $join->on('hashtags.tissue_id', '=', 'tissues.id');
            })
            ->leftJoin('casts AS choco_casts', 'choco_casts.id', '=', 'tissues.cast_id')
            ->leftJoin('yoasobi_casts AS night_casts', 'night_casts.id', '=', 'tissues.night_cast_id')
            ->leftJoinSub($chocoMypageQuery, 'choco_mypages', 'choco_mypages.id', '=', 'tissues.mypage_id')
            ->leftJoinSub($chocoGuestQuery, 'choco_guests', 'choco_guests.id', '=', 'tissues.guest_id')
            ->select(
                'tissues.*',
                'hashtags.hashtag_id',
                DB::raw("
                    ROW_NUMBER() OVER (
                        PARTITION BY hashtags.hashtag_id, tissues.mypage_id, tissues.cast_id, tissues.night_cast_id, tissues.guest_id
                        ORDER BY
                            (IFNULL(tissues.good_count, 0) + IFNULL(tissues.add_good_count, 0)) DESC,
                            tissues.view_count DESC
                    ) as show_num
                ")
            )
            ->where('hashtags.hashtag_id', '=', $hashtagId)
            ->where(function ($query) {
                $query
                    ->whereNotNull('choco_casts.id')
                    ->orWhereNotNull('night_casts.id')
                    ->orWhereNotNull('choco_guests.id')
                    ->orWhereNotNull('choco_mypages.id');
            });

        $query = Tissue::query()
            ->fromSub($tissueShowNumQuery, 'tissues')
            ->select('*')
            ->where('tissues.show_num', DB::raw(1))
            ->where(function ($query) use ($chocoCastIds, $nightCastIds, $chocoMypageIds, $chocoGuestIds) {
                $query
                    ->when(!empty($chocoCastIds), function ($query) use ($chocoCastIds) {
                        $query->whereIn('tissues.cast_id', $chocoCastIds);
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
