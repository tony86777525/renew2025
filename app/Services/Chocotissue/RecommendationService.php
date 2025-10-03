<?php

namespace App\Services\Chocotissue;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Repositories\Chocotissue\TissueRepository;
use App\Repositories\Chocotissue\ListRepository;
use App\Traits\Chocotissue\TissueEnricher;

class RecommendationService
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
     * Get recommendation data.
     *
     * @param boolean      $isPC   Is PC.
     * @param integer      $page   Page.
     * @param integer|null $prefId Prefecture ID.
     * @return Collection
     * @throws Exception
     */
    public function getRecommendationData(
        bool $isPC = true,
        int $page = 1,
        ?int $prefId = null
    ): Collection {
        $this->validatePage($page);
        $this->validatePref($prefId);

        $limit = 30;
        $offset = ($page - 1) * $limit;

        try {
            if ($isPC) {
                $data = $this->listRepository->getPcRecommendations($limit, $offset, $prefId);
            } else {
                $data = $this->listRepository->getSpRecommendations($limit, $offset, $prefId);
            }

            if ($data->isEmpty()) {
                return collect();
            }

            return $this->enrichDataWithTissues($data);
        } catch (Exception $e) {
            Log::error('Failed to get recommendation data', [
                'isPC' => $isPC,
                'page' => $page,
                'prefId' => $prefId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * @param integer $page
     * @return void
     */
    private function validatePage(int $page): void
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be greater than 0');
        }
    }

    /**
     * @param integer|null $prefId
     * @return void
     */
    private function validatePref(?int $prefId = null): void
    {
        if (!is_null($prefId) && $prefId < 1) {
            throw new \InvalidArgumentException('PrefId must be positive');
        }
    }
}
