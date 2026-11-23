<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class BusinessDate
{
    public static function today(): string
    {
        return CarbonImmutable::now('Asia/Jakarta')->toDateString();
    }
}
