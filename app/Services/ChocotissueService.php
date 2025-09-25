<?php

namespace App\Services;

use App\Models\Chocolat\Tissue;
use App\Repositories\Chocotissue\ListRepository;
use App\Repositories\Chocotissue\TissueRepository;
use App\Repositories\Chocotissue\HashtagRepository;

class ChocotissueService
{
    protected ListRepository $listRepository;
    protected TissueRepository $tissueRepository;
    protected HashtagRepository $hashtagRepository;

    public function __construct(
        ListRepository $listRepository,
        TissueRepository $tissueRepository,
        HashtagRepository $hashtagRepository
    ) {
        $this->listRepository = $listRepository;
        $this->tissueRepository = $tissueRepository;
        $this->hashtagRepository = $hashtagRepository;
    }

    /**
     * Enrich data with tissue details
     *
     * @param integer      $page   Page.
     * @param integer|null $prefId Prefecture ID.
     * @return \Illuminate\Support\Collection
     */
    public function getTimeline(
        int $page = 1,
        int $prefId = null
    ): \Illuminate\Support\Collection {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be greater than 0');
        }

        $limit = 30;
        $offset = ($page - 1) * $limit;

        $data = $this->listRepository->getTimeline($limit, $offset, $prefId);

        if ($data->isEmpty()) {
            return collect([]);
        }

        return $this->enrichDataWithTissues($data);
    }

    /**
     * Get Recommendations
     *
     * @param boolean      $isPC   Is PC.
     * @param integer      $page   Page.
     * @param integer|null $prefId Prefecture ID.
     * @return \Illuminate\Support\Collection
     */
    public function getRecommendations(
        bool $isPC = true,
        int $page = 1,
        int $prefId = null
    ): \Illuminate\Support\Collection {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be greater than 0');
        }

        $limit = 30;
        $offset = ($page - 1) * $limit;

        try {
            if ($isPC) {
                $data = $this->listRepository->getPcRecommendations($limit, $offset, $prefId);
            } else {
                $data = $this->listRepository->getSpRecommendations($limit, $offset, $prefId);
            }

            if ($data->isEmpty()) {
                return collect([]);
            }

            return $this->enrichDataWithTissues($data);
        } catch (\Exception $e) {
            // 記錄業務層錯誤
            \Log::error('Failed to get recommendations', [
                'isPC' => $isPC,
                'page' => $page,
                'prefId' => $prefId,
                'error' => $e->getMessage()
            ]);

            // 重新拋出，讓 Controller 處理
            throw $e;
        }
    }

    public function getUserRankings(
        int $page = 1,
        int $prefId = null
    ): \Illuminate\Support\Collection {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be greater than 0');
        }

        $limit = 30;
        $offset = ($page - 1) * $limit;

        $data = $this->listRepository->getUserRankings($limit, $offset, $prefId);

        if ($data->isEmpty()) {
            return collect([]);
        }

        return $this->enrichUserDataWithTissues($data);
    }

    public function getShopRankings(
        array $displayedChocoShopTableIds = [],
        array $displayedNightShopTableIds = [],
        int $page = 1,
        int $prefId = null
    ): \Illuminate\Support\Collection {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be greater than 0');
        }

        $limit = 10;

        $data = $this->listRepository->getShopRankings([], []);

        if ($data->isEmpty()) {
            return collect([]);
        }

        if (isset($prefId) && !empty($prefId)) {
            $data = $data
                ->filter(function ($shop) use ($prefId) {
                    if (!empty($shop->choco_shop_pref_id)) {
                        return $shop->choco_shop_pref_id == $prefId;
                    }
                    return $shop->night_shop_pref_id == $prefId;
                });

            if ($data->isEmpty()) {
                return collect([]);
            }
        }

        // get top 10 of ranking shops
        $shops = $data
            ->filter(function ($shop) use (
                $displayedChocoShopTableIds,
                $displayedNightShopTableIds
            ) {
                if (!empty($shop->choco_shop_table_id)) {
                    return !in_array($shop->choco_shop_table_id, $displayedChocoShopTableIds, true);
                }
                return !in_array($shop->night_shop_table_id, $displayedNightShopTableIds, true);
            })
            ->slice(0, $limit);

        if ($shops->isEmpty()) {
            return collect([]);
        }

        $chocoShopTableIds = $shops->pluck('choco_shop_table_id')->filter()->toArray();
        $nightShopTableIds = $shops->pluck('night_shop_table_id')->filter()->toArray();

        $tissues = $this->tissueRepository->getShopRankingTopTissueOfUsers($chocoShopTableIds, $nightShopTableIds);

        $shops->each(function ($shop) use ($tissues) {
            $currentTissues = $tissues->filter(function ($tissue) use ($shop) {
                $chocoShopTableId = !empty($tissue->choco_shop_table_id) ? $tissue->choco_shop_table_id : 0;
                $nightShopTableId = !empty($tissue->night_shop_table_id) ? $tissue->night_shop_table_id : 0;

                return (!empty($shop->choco_shop_table_id) && $chocoShopTableId == $shop->choco_shop_table_id)
                    || (!empty($shop->night_shop_table_id) && $nightShopTableId == $shop->night_shop_table_id);
            });

            $shop->tissues = $currentTissues;
        });

        return $shops;
    }

    public function getShopRankingDetail(
        bool $isTimeline = true,
        int $chocoShopTableId = null,
        int $nightShopTableId = null,
        int $page = 1
    ): \Illuminate\Support\Collection {
        if (empty($chocoShopTableId) && empty($nightShopTableId)) {
            throw new \InvalidArgumentException('Shop must not be null');
        }

        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be greater than 0');
        }

        $limit = 30;
        $offset = ($page - 1) * $limit;

        if ($isTimeline === true) {
            $data = $this->listRepository->getShopRankingDetailTimeline(
                [$chocoShopTableId],
                [$nightShopTableId],
                $limit,
                $offset
            );
            return $this->enrichDataWithTissues($data);
        } else {
            $data = $this->listRepository->getShopRankingDetailRanking(
                [$chocoShopTableId],
                [$nightShopTableId],
                $limit,
                $offset
            );
            return $this->enrichUserDataWithTissues($data);
        }
    }

    public function getHashtags(
        array $displayedHashtagIds
    ): \Illuminate\Support\Collection {
        $limit = 0;

        $data = $this->listRepository->getHashtags($displayedHashtagIds, $limit);

        if ($data->isEmpty()) {
            return collect([]);
        }

        $hashtagIds = $data->pluck('hashtag_id')->filter()->toArray();
        $hashtags = $this->hashtagRepository->getHashtags($hashtagIds);

        if ($hashtags->isEmpty()) {
            return collect([]);
        }

        $tissues = $this->tissueRepository->getHashtagTopTissueOfUsers($hashtagIds);

        $data->each(function ($row) use ($hashtags, $tissues) {
            $row->hashtag = $hashtags->firstWhere('id', $row->hashtag_id);

            $row->tissues = $tissues->filter(function ($tissue) use ($row) {
                return $tissue->hashtag_id === $row->hashtag_id;
            });
        });

        return $data;
    }

    public function getHashtagDetail(
        bool $isTimeline = true,
        int $hashtagId = null,
        int $page = 1
    ): \Illuminate\Support\Collection {
        if (empty($hashtagId) && empty($hashtagId)) {
            throw new \InvalidArgumentException('hashtag must not be null');
        }

        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be greater than 0');
        }

        $limit = 30;
        $offset = ($page - 1) * $limit;

        if ($isTimeline === true) {
            $data = $this->listRepository->getHashtagDetailTimeline($hashtagId, $limit, $offset);
            return $this->enrichDataWithTissues($data);
        } else {
            $data = $this->listRepository->getHashtagDetailRanking($hashtagId, $limit, $offset);
            if ($data->isEmpty()) {
                return collect([]);
            }

            $chocoCastIds = $data->pluck('choco_cast_id')->filter()->toArray();
            $nightCastIds = $data->pluck('night_cast_id')->filter()->toArray();
            $chocoMypageIds = $data->pluck('choco_mypage_id')->filter()->toArray();
            $chocoGuestIds = $data->pluck('choco_guest_id')->filter()->toArray();

            if (empty($chocoCastIds) && empty($nightCastIds) && empty($chocoMypageIds) && empty($chocoGuestIds)) {
                return collect([]);
            }

            $tissues = $this->tissueRepository->getHashtagDetailRankTopTissueOfUsers(
                $hashtagId,
                $chocoCastIds,
                $nightCastIds,
                $chocoMypageIds,
                $chocoGuestIds
            );

            if ($tissues->isEmpty()) {
                return collect([]);
            }

            $data->each(function ($row) use ($tissues) {
                if (!empty($row->choco_cast_id)) {
                    $row->tissue = $tissues->firstWhere('cast_id', $row->choco_cast_id);
                } elseif (!empty($row->night_cast_id)) {
                    $row->tissue = $tissues->firstWhere('night_cast_id', $row->night_cast_id);
                } elseif (!empty($row->choco_mypage_id)) {
                    $row->tissue = $tissues->firstWhere('mypage_id', $row->choco_mypage_id);
                } elseif (!empty($row->choco_guest_id)) {
                    $row->tissue = $tissues->firstWhere('guest_id', $row->choco_guest_id);
                }
            });

            return $data;
        }
    }

    public function getTissues(
        array $tissueIds,
        int $page = 1
    ): \Illuminate\Support\Collection {
        if (empty($tissueIds)) {
            throw new \InvalidArgumentException('Tissue must not be null');
        }

        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be greater than 0');
        }

        $limit = 30;
        $offset = ($page - 1) * $limit;

        $data = $this->listRepository->getTissues($tissueIds, $limit, $offset);
        return $this->enrichDataWithTissues($data);
    }

    private function enrichDataWithTissues($data): \Illuminate\Support\Collection
    {
        $tissues = $this->attachTissueData($data, Tissue::TISSUE_TYPE_GIRL);
        $mensTissues = $this->attachTissueData($data, Tissue::TISSUE_TYPE_MEN);

        if ($tissues->isNotEmpty() || $mensTissues->isNotEmpty()) {
            $data->each(function ($row) use ($tissues, $mensTissues) {
                if ($row->tissue_type === Tissue::TISSUE_TYPE_GIRL && $tissues->isNotEmpty()) {
                    $row->tissue = $tissues->firstWhere('id', $row->tissue_id);
                }
                if ($row->tissue_type === Tissue::TISSUE_TYPE_MEN && $mensTissues->isNotEmpty()) {
                    $row->tissue = $mensTissues->firstWhere('id', $row->tissue_id);
                }
            });
        }

        return $data;
    }

    private function enrichUserDataWithTissues($data): \Illuminate\Support\Collection
    {
        if ($data->isEmpty()) {
            return collect([]);
        }

        $chocoCastIds = $data->pluck('choco_cast_id')->filter()->toArray();
        $nightCastIds = $data->pluck('night_cast_id')->filter()->toArray();
        $chocoMypageIds = $data->pluck('choco_mypage_id')->filter()->toArray();
        $chocoGuestIds = $data->pluck('choco_guest_id')->filter()->toArray();

        if (empty($chocoCastIds) && empty($nightCastIds) && empty($chocoMypageIds) && empty($chocoGuestIds)) {
            return collect([]);
        }

        $tissues = $this->tissueRepository->getUserRankingTopTissueOfUsers(
            $chocoCastIds,
            $nightCastIds,
            $chocoMypageIds,
            $chocoGuestIds
        );

        if ($tissues->isEmpty()) {
            return collect([]);
        }

        $data->each(function ($row) use ($tissues) {
            if (!empty($row->choco_cast_id)) {
                $row->tissue = $tissues->firstWhere('user_id_for_choco_cast', $row->choco_cast_id);
            } elseif (!empty($row->night_cast_id)) {
                $row->tissue = $tissues->firstWhere('night_cast_id', $row->night_cast_id);
            } elseif (!empty($row->choco_mypage_id)) {
                $row->tissue = $tissues->firstWhere('mypage_id', $row->choco_mypage_id);
            } elseif (!empty($row->choco_guest_id)) {
                $row->tissue = $tissues->firstWhere('guest_id', $row->choco_guest_id);
            }
        });

        return $data;
    }

    private function attachTissueData($data, string $tissueType): \Illuminate\Support\Collection
    {
        if (!in_array($tissueType, [Tissue::TISSUE_TYPE_GIRL, Tissue::TISSUE_TYPE_MEN], true)) {
            return collect();
        }

        $tissueIds = $data->where('tissue_type', $tissueType)->pluck('tissue_id')->filter()->toArray();

        if (empty($tissueIds)) {
            return collect();
        }

        return $tissueType === Tissue::TISSUE_TYPE_GIRL
            ? $this->tissueRepository->getTissues($tissueIds)
            : $this->tissueRepository->getMensTissues($tissueIds);
    }

    // 提取共用的頁面驗證
    private function validatePage(int $page): void
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be greater than 0');
        }
    }

    // 提取共用的業務驗證
    private function validateBusinessRules(int $page, ?int $prefId = null): void
    {
        $this->validatePage($page);

        if ($prefId && $prefId < 1) {
            throw new \InvalidArgumentException('PrefId must be positive');
        }
    }
}
