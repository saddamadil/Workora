<?php

namespace App\Support;

/**
 * Turns "treatment + a rate" into the tax lines an invoice carries. It only does the
 * arithmetic of presenting a rate the issuer chose (for instance splitting a GST rate in
 * half for CGST and SGST). It does not decide what tax applies.
 */
class TaxPlan
{
    /** @return array<int, array{label: string, rate: float}> */
    public static function lines(string $treatment, float $rate, ?string $customLabel = null): array
    {
        $rate = round(max(0, $rate), 2);

        return match ($treatment) {
            'gst_intra' => $rate > 0 ? [['label' => 'CGST', 'rate' => round($rate / 2, 2)], ['label' => 'SGST', 'rate' => round($rate / 2, 2)]] : [],
            'gst_inter', 'export_igst' => $rate > 0 ? [['label' => 'IGST', 'rate' => $rate]] : [],
            'vat' => $rate > 0 ? [['label' => 'VAT', 'rate' => $rate]] : [],
            'custom' => $rate > 0 ? [['label' => trim((string) $customLabel) ?: 'Tax', 'rate' => $rate]] : [],
            default => [],
        };
    }

    /** The single combined rate to show back in a form. */
    public static function totalRate(?array $lines): float
    {
        return round(array_sum(array_column($lines ?? [], 'rate')), 2);
    }

    /** Treatments where a rate box is shown. */
    public static function needsRate(string $treatment): bool
    {
        return in_array($treatment, ['gst_intra', 'gst_inter', 'export_igst', 'vat', 'custom'], true);
    }
}
