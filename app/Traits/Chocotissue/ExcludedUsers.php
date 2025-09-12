<?php

namespace App\Traits\Chocotissue;

use Carbon\Carbon;

trait ExcludedUsers
{
    protected function excludedChocoGuests()
    {
        return [38];
    }

    protected function excludedChocoCasts()
    {
        return [14377, 16624, 14451, 13455];
    }
}