<?php

namespace App\Services\Chocotissue;

use App\Repositories\Chocotissue\HashtagRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Repositories\Chocotissue\TissueRepository;
use App\Repositories\Chocotissue\ListRepository;
use App\Traits\Chocotissue\TissueEnricher;

class HashtagService
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
     * Get hashtag data.
     *
     * @param array $displayedHashtagIds
     * @return Collection
     * @throws Exception
     */
    public function getHashtagData(
        array $displayedHashtagIds
    ): Collection {
        $limit = 10;

        try {
            $data = $this->listRepository->getHashtags($displayedHashtagIds, $limit);

            if ($data->isEmpty()) {
                return collect();
            }

            $hashtagIds = $data->pluck('hashtag_id')->filter()->toArray();
            $hashtags = $this->hashtagRepository->getHashtags($hashtagIds);

            if ($hashtags->isEmpty()) {
                return collect();
            }

            $tissues = $this->tissueRepository->getHashtagTopTissueOfUsers($hashtagIds);

            $data->each(function ($row) use ($hashtags, $tissues) {
                $row->hashtag = $hashtags->firstWhere('id', $row->hashtag_id);

                $row->tissues = $tissues->filter(function ($tissue) use ($row) {
                    return $tissue->hashtag_id === $row->hashtag_id;
                });
            });

            return $data;
        } catch (Exception $e) {
            Log::error('Failed to get hashtag data', [
                'displayedHashtagIds' => $displayedHashtagIds,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
