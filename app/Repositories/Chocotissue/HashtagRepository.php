<?php

namespace App\Repositories\Chocotissue;

use Illuminate\Database\Eloquent\Collection;
use App\Models\Chocolat\Hashtag;

class HashtagRepository
{
    public function getHashtags(array $ids): Collection
    {
        $query = Hashtag::query()
            ->whereIn('id', $ids);

        return $query->get();
    }
}
