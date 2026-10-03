<?php

namespace App\Support;

/** Countries offered in forms (ISO 3166-1 alpha-2). A common subset, not the full registry. */
class Countries
{
    public const LIST = [
        'IN' => 'India', 'US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada', 'AU' => 'Australia',
        'AE' => 'United Arab Emirates', 'SG' => 'Singapore', 'DE' => 'Germany', 'FR' => 'France', 'NL' => 'Netherlands',
        'ES' => 'Spain', 'IT' => 'Italy', 'IE' => 'Ireland', 'BE' => 'Belgium', 'AT' => 'Austria', 'CH' => 'Switzerland',
        'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark', 'FI' => 'Finland', 'PL' => 'Poland', 'PT' => 'Portugal',
        'CZ' => 'Czechia', 'RO' => 'Romania', 'GR' => 'Greece', 'HU' => 'Hungary', 'BG' => 'Bulgaria', 'HR' => 'Croatia',
        'NZ' => 'New Zealand', 'JP' => 'Japan', 'KR' => 'South Korea', 'CN' => 'China', 'HK' => 'Hong Kong', 'MY' => 'Malaysia',
        'ID' => 'Indonesia', 'TH' => 'Thailand', 'VN' => 'Vietnam', 'PH' => 'Philippines', 'PK' => 'Pakistan', 'BD' => 'Bangladesh',
        'LK' => 'Sri Lanka', 'NP' => 'Nepal', 'SA' => 'Saudi Arabia', 'QA' => 'Qatar', 'KW' => 'Kuwait', 'OM' => 'Oman',
        'BH' => 'Bahrain', 'IL' => 'Israel', 'TR' => 'Turkey', 'EG' => 'Egypt', 'ZA' => 'South Africa', 'NG' => 'Nigeria',
        'KE' => 'Kenya', 'GH' => 'Ghana', 'BR' => 'Brazil', 'MX' => 'Mexico', 'AR' => 'Argentina', 'CL' => 'Chile', 'CO' => 'Colombia',
        'RU' => 'Russia', 'UA' => 'Ukraine',
    ];

    /** Members of the EU VAT area, which share a "VAT number" field. */
    public const EU = ['DE', 'FR', 'NL', 'ES', 'IT', 'IE', 'BE', 'AT', 'SE', 'DK', 'FI', 'PL', 'PT', 'CZ', 'RO', 'GR', 'HU', 'BG', 'HR'];

    public static function name(?string $code): string
    {
        return $code ? (self::LIST[strtoupper($code)] ?? strtoupper($code)) : '';
    }

    /** Indian financial years run April to March; elsewhere a calendar year is used. */
    public static function hasAprilFinancialYear(?string $code): bool
    {
        return strtoupper((string) $code) === 'IN';
    }
}
