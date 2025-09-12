<?php

namespace App\Traits\Chocotissue;

use Carbon\Carbon;

trait DateWindows
{
    protected function championshipStartDatetime()
    {
        return new Carbon('2025-07-15 00:00:00');
    }

    protected function nowDatetime()
    {
        return Carbon::now();
    }

    protected function weekStartDatetime()
    {
        return (new Carbon('monday this week'));
    }

    protected function lastWeekStartDate()
    {
        return (new Carbon('monday this week'))->subWeek();
    }
}