<?php

namespace App\Traits\Chocotissue;

use Illuminate\Support\Collection;
use App\Models\Chocolat\Tissue;
use App\Repositories\Chocotissue\TissueRepository;

trait TissueEnricher
{
    protected TissueRepository $tissueRepository;

    /**
     * Enrich data with full tissue models.
     *
     * @param Collection $data Collection of items with tissue_id and tissue_type.
     * @return Collection
     */
    private function enrichDataWithTissues(Collection $data): Collection
    {
        if ($data->isEmpty()) {
            return $data;
        }

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

    /**
     * Enrich user ranking data with their top tissue.
     *
     * @param Collection $data
     * @return Collection
     */
    private function enrichUserDataWithTissues($data): \Illuminate\Support\Collection
    {
        if ($data->isEmpty()) {
            return collect();
        }

        $chocoCastIds = $data->pluck('choco_cast_id')->filter()->toArray();
        $nightCastIds = $data->pluck('night_cast_id')->filter()->toArray();
        $chocoMypageIds = $data->pluck('choco_mypage_id')->filter()->toArray();
        $chocoGuestIds = $data->pluck('choco_guest_id')->filter()->toArray();

        if (empty($chocoCastIds) && empty($nightCastIds) && empty($chocoMypageIds) && empty($chocoGuestIds)) {
            return collect();
        }

        $tissues = $this->tissueRepository->getUserRankingTopTissueOfUsers(
            $chocoCastIds,
            $nightCastIds,
            $chocoMypageIds,
            $chocoGuestIds
        );

        if ($tissues->isEmpty()) {
            return collect();
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

    /**
     * Fetch tissue models based on type.
     *
     * @param Collection $data
     * @param string $tissueType
     * @return Collection
     */
    private function attachTissueData(Collection $data, string $tissueType): Collection
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
}
