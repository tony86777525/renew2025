<?php

namespace App\Services\Chocotissue;

use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\Chocolat\Tissue;
use App\Repositories\Chocotissue\TissueRepository;
use App\Repositories\Chocotissue\ListRepository;
use App\Traits\Chocotissue\TissueEnricher;

class DetailService
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
     * Get detail data.
     *
     * @param integer $tissueId
     * @return object
     * @throws Exception
     */
    public function getDetailData(
        int $tissueId
    ): object {
        if (empty($tissueId)) {
            throw new \InvalidArgumentException('Tissue must not be null');
        }

        try {
            $baseTissueData = $this->listRepository->findTissueById($tissueId);

            if (is_null($baseTissueData)) {
                return (object)[];
            }

            $enrichedTissues = $this->enrichDataWithTissues(collect([$baseTissueData]));

            return $enrichedTissues->first() ?? (object)[];
        } catch (Exception $e) {
            Log::error('Failed to get detail data', [
                'tissueId' => $tissueId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
