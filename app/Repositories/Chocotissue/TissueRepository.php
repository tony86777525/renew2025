<?php

namespace App\Repositories\Chocotissue;

use Illuminate\Support\Facades\DB;
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

    public function getUserRankingTopTissueOfUsers(
        array $chocoCastIds,
        array $nightCastIds,
        array $chocoMypageIds,
        array $chocoGuestIds
    ): \Illuminate\Database\Eloquent\Collection {
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

        $build = new UserTopTissueQueryBuilder;
        $userTissueQuery = $build->buildUserTissueQuery($tissueQuery, $chocoMypageQuery, $chocoGuestQuery);
        $orderTissues = $build->buildUserRankingOrderTissueQuery($userTissueQuery);

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

    public function getShopRankingTopTissueOfUsers(
        array $chocoShopTableIds,
        array $nightShopTableIds
    ): \Illuminate\Database\Eloquent\Collection {
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

        $tissueQuery->addSelect(DB::raw("({$tissueCommentLastOneQuery->toSql()}) AS last_comment_date"));

        $build = new UserTopTissueQueryBuilder;
        $userTissueQuery = $build->buildUserTissueQuery($tissueQuery, $chocoMypageQuery, $chocoGuestQuery);
        $orderTissues = $build->buildShopRankingOrderTissueQuery($userTissueQuery);

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

    public function getShopRankingDetailTopTissueOfUsers(
        array $chocoCastIds,
        array $nightCastIds,
        array $chocoMypageIds,
        array $chocoGuestIds
    ): \Illuminate\Database\Eloquent\Collection {
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

        $build = new UserTopTissueQueryBuilder;
        $userTissueQuery = $build->buildUserTissueQuery($tissueQuery, $chocoMypageQuery, $chocoGuestQuery);
        $orderTissues = $build->buildShopRankingDetailOrderTissueQuery($userTissueQuery);

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

    public function getHashtagTopTissueOfUsers(
        array $hashtagIds
    ): \Illuminate\Database\Eloquent\Collection {
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

        $tissueQuery->addSelect(DB::raw("({$tissueCommentLastOneQuery->toSql()}) AS last_comment_date"));

        $tissueQuery = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->query()
            ->fromSub($tissueQuery, 'tissues')
            ->select(
                'tissues.*',
                DB::raw("
                    (
                        CASE
                            WHEN tissues.last_comment_date IS NOT NULL THEN
                                tissues.last_comment_date
                            ELSE
                                tissues.release_date
                        END
                    ) AS last_update_datetime
                ")
            );

        $tissueShowNumQuery = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
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

    public function getHashtagDetailRankTopTissueOfUsers(
        int $hashtagId,
        array $chocoCastIds,
        array $nightCastIds,
        array $chocoMypageIds,
        array $chocoGuestIds
    ): \Illuminate\Database\Eloquent\Collection {
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

        $tissueShowNumQuery = DB::connection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
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
