<?php

namespace App\Repositories\Chocotissue;

use App\Models\Chocolat\Hashtag;

class HashtagRepository
{
    public function getHashtags(array $ids): \Illuminate\Database\Eloquent\Collection
    {
        $query = Hashtag::query()
            ->whereIn('id', $ids);

        return $query->get();
    }
}
