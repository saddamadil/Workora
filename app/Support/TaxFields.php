<?php

namespace App\Support;

/**
 * Which tax and registration numbers a person or company can enter, by country.
 *
 * This only decides which boxes to show and how to sanity-check their format. It does
 * not know anyone's tax obligations: whether and how tax applies is for the person
 * issuing the invoice, with their accountant, to decide.
 */
class TaxFields
{
    /** @return array<int, array{key: string, label: string, hint: string, pattern: ?string}> */
    public static function forCountry(?string $code): array
    {
        $code = strtoupper((string) $code);

        $gstin = '/^\d{2}[A-Z]{5}\d{4}[A-Z][A-Z\d]Z[A-Z\d]$/';
        $pan = '/^[A-Z]{5}\d{4}[A-Z]$/';

        return match (true) {
            $code === 'IN' => [
                ['key' => 'gstin', 'label' => 'GSTIN', 'hint' => '15 characters, e.g. 27AAPFU0939F1ZV', 'pattern' => $gstin],
                ['key' => 'pan', 'label' => 'PAN', 'hint' => '10 characters, e.g. ABCDE1234F', 'pattern' => $pan],
                ['key' => 'registration', 'label' => 'Registration number (CIN / LLPIN / Udyam)', 'hint' => '', 'pattern' => null],
                ['key' => 'other', 'label' => 'Other tax ID', 'hint' => '', 'pattern' => null],
            ],
            in_array($code, Countries::EU, true), $code === 'GB' => [
                ['key' => 'vat', 'label' => 'VAT number', 'hint' => 'with country prefix, e.g. DE123456789', 'pattern' => null],
                ['key' => 'registration', 'label' => 'Company registration number', 'hint' => '', 'pattern' => null],
                ['key' => 'tax_id', 'label' => 'Tax ID', 'hint' => '', 'pattern' => null],
            ],
            $code === 'US' => [
                ['key' => 'tax_id', 'label' => 'EIN / Tax ID', 'hint' => '', 'pattern' => null],
                ['key' => 'registration', 'label' => 'State registration number', 'hint' => '', 'pattern' => null],
            ],
            $code === 'AE' => [
                ['key' => 'vat', 'label' => 'TRN (VAT registration)', 'hint' => '15 digits', 'pattern' => null],
                ['key' => 'registration', 'label' => 'Trade licence number', 'hint' => '', 'pattern' => null],
            ],
            $code === 'AU' => [
                ['key' => 'tax_id', 'label' => 'ABN', 'hint' => '11 digits', 'pattern' => null],
                ['key' => 'vat', 'label' => 'GST registration', 'hint' => '', 'pattern' => null],
            ],
            $code === 'CA' => [
                ['key' => 'vat', 'label' => 'GST/HST number', 'hint' => '', 'pattern' => null],
                ['key' => 'tax_id', 'label' => 'Business number', 'hint' => '', 'pattern' => null],
            ],
            $code === 'SG' => [
                ['key' => 'vat', 'label' => 'GST registration number', 'hint' => '', 'pattern' => null],
                ['key' => 'registration', 'label' => 'UEN', 'hint' => '', 'pattern' => null],
            ],
            default => [
                ['key' => 'tax_id', 'label' => 'Tax ID', 'hint' => '', 'pattern' => null],
                ['key' => 'registration', 'label' => 'Business registration number', 'hint' => '', 'pattern' => null],
                ['key' => 'other', 'label' => 'Other tax ID', 'hint' => '', 'pattern' => null],
            ],
        };
    }

    /** Every country's fields, keyed by country code, for the form's show/hide script. */
    public static function allForForms(): array
    {
        $out = ['default' => self::forCountry('ZZ')];
        foreach (array_keys(Countries::LIST) as $code) {
            $out[$code] = self::forCountry($code);
        }

        return $out;
    }

    /**
     * Keep only the numbers valid for this country, uppercased and trimmed.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: array<string, string>, 1: array<string, string>} [clean values, errors by key]
     */
    public static function clean(?string $country, array $input): array
    {
        $clean = [];
        $errors = [];

        foreach (self::forCountry($country) as $field) {
            $value = strtoupper(trim((string) ($input[$field['key']] ?? '')));
            if ($value === '') {
                continue;
            }
            if ($field['pattern'] && ! preg_match($field['pattern'], $value)) {
                $errors[$field['key']] = $field['label'].' does not look right. '.$field['hint'];

                continue;
            }
            $clean[$field['key']] = $value;
        }

        return [$clean, $errors];
    }

    /** "GSTIN: 27AAP…, PAN: ABCDE1234F" for a document. */
    public static function lines(?string $country, ?array $ids): array
    {
        $out = [];
        foreach (self::forCountry($country) as $field) {
            if (! empty($ids[$field['key']])) {
                $out[] = [preg_replace('/ \(.*\)$/', '', $field['label']), $ids[$field['key']]];
            }
        }

        return $out;
    }
}
