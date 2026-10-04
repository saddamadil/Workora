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

if (! function_exists('format_message')) {
    /** Message text made safe for display: escaped, with links clickable and @mentions highlighted. */
    function format_message(string $body): \Illuminate\Support\HtmlString
    {
        $safe = e($body);
        $safe = preg_replace_callback('~(https?://[^\s<]+)~i', function ($m) {
            $url = rtrim($m[1], '.,;:!?)');
            $tail = substr($m[1], strlen($url));

            return '<a href="'.$url.'" target="_blank" rel="noopener noreferrer nofollow" class="underline">'.$url.'</a>'.$tail;
        }, $safe);
        $safe = preg_replace('/(^|\s)@([\p{L}][\p{L}\p{N}_.-]{1,30})/u', '$1<span class="rounded bg-brand-100 px-1 font-medium text-brand-700">@$2</span>', $safe);

        return new \Illuminate\Support\HtmlString(nl2br($safe));
    }
}
