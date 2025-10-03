<?php

namespace App\Services\Chocotissue;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Repositories\Chocotissue\TissueRepository;
use App\Repositories\Chocotissue\ListRepository;
use App\Traits\Chocotissue\TissueEnricher;

class ShopRankingService
{
    use TissueEnricher;

    protected ListRepository $listRepository;

    public function __construct(
        ListRepository $listRepository,
        TissueRepository $tissueRepository
    ) {
        $this->listRepository = $listRepository;
        $this->tissueRepository = $tissueRepository;
    }

    /**
     * Get shopRanking data.
     *
     * @param array        $displayedChocoShopTableIds
     * @param array        $displayedNightShopTableIds
     * @param integer      $page
     * @param integer|null $prefId
     * @return Collection
     * @throws Exception
     */
    public function getShopRankingData(
        array $displayedChocoShopTableIds = [],
        array $displayedNightShopTableIds = [],
        int $page = 1,
        ?int $prefId = null
    ): Collection {
        $this->validatePage($page);
        $this->validatePref($prefId);

        $limit = 10;

        try {
            $data = $this->listRepository->getShopRankings(
                $displayedChocoShopTableIds,
                $displayedNightShopTableIds,
                $limit,
                $prefId
            );

            if ($data->isEmpty()) {
                return collect();
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
                    return collect();
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
                return collect();
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
        } catch (Exception $e) {
            Log::error('Failed to get shop ranking data', [
                'page' => $page,
                'prefId' => $prefId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * @param int $page
     * @return void
     */
    private function validatePage(int $page): void
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be greater than 0');
        }
    }

    /**
     * @param int|null $prefId
     * @return void
     */
    private function validatePref(?int $prefId = null): void
    {
        if (!is_null($prefId) && $prefId < 1) {
            throw new \InvalidArgumentException('PrefId must be positive');
        }
    }
}
