<?php

namespace App\Support;

/**
 * Money is stored as integer minor units (paise, cents). This is the only place
 * that converts between that and what a person types or reads.
 */
class Money
{
    public const CURRENCIES = [
        'INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£',
        'AED' => 'AED ', 'AUD' => 'A$', 'CAD' => 'C$', 'SGD' => 'S$',
    ];

    public static function format(?int $minor, string $currency = 'INR'): string
    {
        $symbol = self::CURRENCIES[$currency] ?? $currency.' ';
        $value = ($minor ?? 0) / 100;

        return ($value < 0 ? '-' : '').$symbol.number_format(abs($value), 2);
    }

    /** "1,250.50" or 1250.5 to 125050. Blank or invalid is null. */
    public static function toMinor(string|int|float|null $input): ?int
    {
        if ($input === null || $input === '') {
            return null;
        }

        $clean = str_replace([',', ' '], '', (string) $input);

        return is_numeric($clean) ? (int) round(((float) $clean) * 100) : null;
    }

    /** 125050 to "1250.50", for pre-filling form fields. */
    public static function toInput(?int $minor): string
    {
        return $minor === null ? '' : number_format($minor / 100, 2, '.', '');
    }
}
