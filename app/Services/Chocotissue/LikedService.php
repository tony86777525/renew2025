<?php

namespace App\Services\Chocotissue;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Repositories\Chocotissue\TissueRepository;
use App\Repositories\Chocotissue\ListRepository;
use App\Traits\Chocotissue\TissueEnricher;

class LikedService
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
     * Get liked data.
     *
     * @param array $tissueIds
     * @param int $page
     * @param int|null $prefId
     * @return Collection
     * @throws Exception
     */
    public function getLikedData(
        array $tissueIds,
        int $page = 1,
        ?int $prefId = null
    ): Collection {
        $this->validatePage($page);
        $this->validatePref($prefId);

        if (empty($tissueIds)) {
            throw new \InvalidArgumentException('Tissue must not be null');
        }

        $limit = 30;
        $offset = ($page - 1) * $limit;

        try {
            $data = $this->listRepository->findTissuesByIds($tissueIds, $limit, $offset);
            return $this->enrichDataWithTissues($data);
        } catch (Exception $e) {
            Log::error('Failed to get liked tissue data', [
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
