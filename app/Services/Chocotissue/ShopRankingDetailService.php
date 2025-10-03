<?php

namespace App\Services\Chocotissue;

use App\Models\Chocolat\ShopMain as ChocoShop;
use App\Models\Night\Shop as NightShop;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Repositories\Chocotissue\TissueRepository;
use App\Repositories\Chocotissue\ListRepository;
use App\Traits\Chocotissue\TissueEnricher;

class ShopRankingDetailService
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
     * Get shop ranking detail data.
     *
     * @param bool         $isTimeline
     * @param integer|null $chocoShopTableId
     * @param integer|null $nightShopTableId
     * @param integer      $page
     * @return array
     * @throws Exception
     */
    public function getShopRankingDetailData(
        bool $isTimeline = true,
        ?int $chocoShopTableId = null,
        ?int $nightShopTableId = null,
        int $page = 1
    ): array {
        $this->validatePage($page);

        if (empty($chocoShopTableId) && empty($nightShopTableId)) {
            throw new \InvalidArgumentException('Shop must not be null');
        }

        $limit = 30;
        $offset = ($page - 1) * $limit;

        try {
            $shop = $this->listRepository->getShopRanking($chocoShopTableId, $nightShopTableId);

            if (!empty($shop->choco_shop_table_id)) {
                $shop->chocoShop = ChocoShop::find($shop->choco_shop_table_id);
            }
            if (!empty($shop->night_shop_table_id)) {
                $shop->nightShop = NightShop::find($shop->night_shop_table_id);
            }

            if ($isTimeline === true) {
                $data = $this->listRepository->getShopRankingDetailTimelines(
                    [$chocoShopTableId],
                    [$nightShopTableId],
                    $limit,
                    $offset
                );

                $casts = $this->enrichDataWithTissues($data);
            } else {
                $data = $this->listRepository->getShopRankingDetailRankings(
                    [$chocoShopTableId],
                    [$nightShopTableId],
                    $limit,
                    $offset
                );

                $casts = $this->enrichUserDataWithTissues($data);
            }

            return [$shop, $casts];
        } catch (Exception $e) {
            Log::error('Failed to get shop ranking data', [
                'isTimeline' => $isTimeline,
                'chocoShopTableId' => $chocoShopTableId,
                'nightShopTableId' => $nightShopTableId,
                'page' => $page,
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
}
