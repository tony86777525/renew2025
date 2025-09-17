<?php

namespace App\Repositories\Chocotissue;

use App\Models\Chocolat\Hashtag;
use App\Traits\Chocotissue\CommonQueries;
use App\Traits\Chocotissue\DateWindows;
use App\Traits\Chocotissue\ExcludedUsers;

class HashtagRepository
{
    use CommonQueries, DateWindows, ExcludedUsers;

    public function getHashtags(array $ids): \Illuminate\Database\Eloquent\Collection
    {
        $query = Hashtag::query()
            ->whereIn('id', $ids);

        return $query->get();
    }
}
