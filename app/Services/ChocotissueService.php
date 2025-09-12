<?php

namespace App\Services;

use App\Models\Chocolat\Tissue;
use App\Repositories\ChocotissueRepository;

class ChocotissueService
{
    protected ChocotissueRepository $repository;

    public function __construct(ChocotissueRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getTimeline(
        int $page = 1,
        int $prefId = null
    ): \Illuminate\Support\Collection {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be greater than 0');
        }

        $limit = 30;
        $offset = ($page - 1) * $limit;

        $data = $this->repository->getTimeline($limit, $offset, $prefId);

        if ($data->isEmpty()) {
            return collect([]);
        }

        return $this->enrichDataWithTissues($data);
    }

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
                $data = $this->repository->getPcRecommendations($limit, $offset, $prefId);
            } else {
                $data = $this->repository->getSpRecommendations($limit, $offset, $prefId);
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

    public function getUserWeeklyRankings(
        int $page = 1,
        int $prefId = null
    ): \Illuminate\Support\Collection {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be greater than 0');
        }

        $limit = 30;
        $offset = ($page - 1) * $limit;

        $data = $this->repository->getUserWeeklyRankings($limit, $offset, $prefId);

        if ($data->isEmpty()) {
            return collect([]);
        }

        return $this->enrichUserDataWithTissues($data);
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

        $data = $this->repository->getUserRankings($limit, $offset, $prefId);

        return $this->enrichUserDataWithTissues($data);
    }

    public function getShopRankings(
        int $page = 1,
        int $prefId = null
    ): \Illuminate\Support\Collection {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be greater than 0');
        }

        $limit = 50;

        $data = $this->repository->getShopRankings([], []);
// dd($data);
        return $data;
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
        
        $tissues = $this->repository->getTopTissueOfUsers($chocoCastIds, $nightCastIds, $chocoMypageIds, $chocoGuestIds);
        
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
            ? $this->repository->getTissues($tissueIds)
            : $this->repository->getMensTissues($tissueIds);
    }
}


