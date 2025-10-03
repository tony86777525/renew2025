<?php

namespace App\Services\Chocotissue;

use App\Repositories\Chocotissue\HashtagRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Repositories\Chocotissue\TissueRepository;
use App\Repositories\Chocotissue\ListRepository;
use App\Traits\Chocotissue\TissueEnricher;

class HashtagDetailService
{
    use TissueEnricher;

    protected ListRepository $listRepository;
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
     * Get hashtag detail data.
     *
     * @param boolean      $isTimeline
     * @param integer|null $hashtagId
     * @param integer      $page
     * @return Collection
     * @throws Exception
     */
    public function getHashtagDetailData(
        bool $isTimeline = true,
        ?int $hashtagId = null,
        int $page = 1
    ): Collection {
        $this->validatePage($page);

        if (empty($hashtagId)) {
            throw new \InvalidArgumentException('hashtag must not be null');
        }

        $limit = 30;
        $offset = ($page - 1) * $limit;

        try {
            if ($isTimeline === true) {
                $data = $this->listRepository->getHashtagDetailTimelines($hashtagId, $limit, $offset);
                return $this->enrichDataWithTissues($data);
            } else {
                $data = $this->listRepository->getHashtagDetailRankings($hashtagId, $limit, $offset);
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

                $tissues = $this->tissueRepository->getHashtagDetailRankTopTissueOfUsers(
                    $hashtagId,
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
        } catch (Exception $e) {
            Log::error('Failed to get hashtag detail data', [
                'displayedHashtagIds' => $isTimeline,
                'hashtagId' => $hashtagId,
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
