<?php

use App\Support\Money;

if (! function_exists('money')) {
    function money(?int $minor, string $currency = 'INR'): string
    {
        return Money::format($minor, $currency);
    }
}

if (! function_exists('hours')) {
    /** Minutes as "2h 05m". */
    function hours(int $minutes): string
    {
        return intdiv($minutes, 60).'h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).'m';
    }
}
