<?php

namespace App\Services;

use App\Models\Client;
use App\Models\FreelancerProfile;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PayoutMethod;
use App\Models\User;
use App\Support\Countries;
use App\Support\TaxFields;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Everything an invoice shows, assembled in one place so the on-screen preview, the printed
 * page and the PDF are always the same document.
 *
 * Who the parties are is "frozen" into the invoice when it is sent (snapshot()), so later
 * edits to a profile cannot change a document that has already been issued. Before that,
 * the document follows the live profiles.
 */
class InvoiceDocument
{
    public function __construct(private ImageStore $images) {}

    /** @return array<string, mixed> */
    public function build(Invoice $invoice): array
    {
        $invoice->loadMissing('items', 'freelancer.freelancerProfile', 'organization', 'client', 'payoutMethod', 'payments');

        $parties = $invoice->snapshot['parties'] ?? $this->parties($invoice);
        $payment = $invoice->snapshot['payment'] ?? $this->payment($invoice);
        $cur = $invoice->currency;

        $items = $invoice->items->map(fn ($i) => [
            'description' => $i->description,
            'quantity' => rtrim(rtrim(number_format((float) $i->quantity, 2, '.', ''), '0'), '.'),
            'unit' => $i->unit,
            'rate' => money($i->unit_rate_minor, $cur),
            'discount' => (float) $i->discount_percent > 0 ? rtrim(rtrim(number_format((float) $i->discount_percent, 2, '.', ''), '0'), '.').'%' : null,
            'amount' => money($i->amount_minor, $cur),
        ])->all();

        $treatment = $invoice->tax_treatment ?: 'none';

        return [
            'template' => in_array($invoice->template, Invoice::TEMPLATES, true) ? $invoice->template : 'professional',
            'title' => in_array($treatment, ['gst_intra', 'gst_inter'], true) && ! empty($parties['from']['tax_map']['gstin']) ? 'TAX INVOICE' : 'INVOICE',
            'number' => $invoice->number,
            'status' => $invoice->displayStatus(),
            'issue_date' => $invoice->issue_date->format('d M Y'),
            'due_date' => $invoice->due_date->format('d M Y'),
            'payment_terms' => $invoice->payment_terms ?: null,
            'period' => $invoice->service_period_start
                ? $invoice->service_period_start->format('d M Y').($invoice->service_period_end ? ' to '.$invoice->service_period_end->format('d M Y') : '')
                : null,
            'currency' => $cur,
            'type' => $invoice->invoice_type,
            'header' => $parties['header'],
            'from' => $parties['from'],
            'bill_to' => $parties['bill_to'],
            'items' => $items,
            // With discounts the subtotal is shown before them, then the discount, so the sum is visible.
            'has_discount' => $invoice->items->contains(fn ($i) => (float) $i->discount_percent > 0),
            'subtotal' => money($invoice->items->contains(fn ($i) => (float) $i->discount_percent > 0) ? $invoice->items->sum(fn ($i) => $i->grossMinor()) : $invoice->subtotal_minor, $cur),
            'discount_total' => money($invoice->items->sum(fn ($i) => $i->grossMinor() - $i->amount_minor), $cur),
            'tax_lines' => collect($invoice->taxBreakdown())->map(fn ($l) => [
                'label' => $l['label'].' @ '.rtrim(rtrim(number_format((float) $l['rate'], 2, '.', ''), '0'), '.').'%',
                'amount' => money($l['amount_minor'], $cur),
            ])->all(),
            'tax_treatment' => Invoice::TREATMENTS[$treatment] ?? null,
            'zero_rated_note' => $treatment === 'export_lut',
            'total' => money($invoice->total_minor, $cur),
            'paid' => $invoice->amount_paid_minor ? money($invoice->amount_paid_minor, $cur) : null,
            'balance' => $invoice->amount_paid_minor ? money($invoice->outstandingMinor(), $cur) : null,
            'domestic' => $invoice->isInternational() ? null : array_filter([
                'place_of_supply' => $invoice->place_of_supply,
                'sac' => $invoice->sac_code,
            ]),
            'international' => $invoice->isInternational() ? array_filter([
                'exchange_rate' => (float) $invoice->exchange_rate > 0 ? rtrim(rtrim(number_format((float) $invoice->exchange_rate, 6, '.', ''), '0'), '.') : null,
                'inr_equivalent' => $invoice->inr_equivalent_minor ? money($invoice->inr_equivalent_minor, 'INR') : null,
                'lut' => $invoice->lut_reference,
                'sac' => $invoice->sac_code,
            ]) : null,
            'payment' => $payment,
            'qr' => $this->qr($invoice, $payment),
            'notes' => $invoice->notes,
            'signature' => $parties['signature_path'] ? $this->images->dataUri($parties['signature_path']) : null,
            'signature_name' => $parties['from']['name'] ?? null,
            'header_image' => ($parties['header']['image_path'] ?? null) ? $this->images->dataUri($parties['header']['image_path']) : null,
            'from_image' => ($parties['from']['image_path'] ?? null) ? $this->images->dataUri($parties['from']['image_path']) : null,
            'bill_image' => ($parties['bill_to']['image_path'] ?? null) ? $this->images->dataUri($parties['bill_to']['image_path']) : null,
        ];
    }

    /** The parties as they are today. Image fields are storage paths, resolved at render time. */
    public function parties(Invoice $invoice): array
    {
        $freelancer = $invoice->freelancer;
        $profile = $freelancer->freelancerProfile;
        $org = $invoice->organization;
        $client = $invoice->bill_to_type === 'client' ? $invoice->client : null;

        $from = $this->freelancerBlock($freelancer, $profile);
        $company = $this->companyBlock($org);
        $billTo = $client ? $this->clientBlock($client) : $company;

        // On the company's letterhead when billing a client; on the freelancer's own when billing the company.
        $header = $client ? $company + ['kind' => 'company'] : $from + ['kind' => 'freelancer'];

        return ['header' => $header, 'from' => $from, 'bill_to' => $billTo, 'signature_path' => $profile?->signature_path];
    }

    public function payment(Invoice $invoice): ?array
    {
        $method = $invoice->payoutMethod;

        if (! $method) {
            return null;
        }

        $lines = [];
        $add = function (string $label, ?string $value) use (&$lines) {
            if (filled($value)) {
                $lines[] = [$label, $value];
            }
        };

        $add('Account holder', $method->detail('account_holder'));
        $add('Bank', $method->detail('bank_name'));
        $add('Branch', $method->detail('branch'));
        $add('Bank address', $method->detail('bank_address'));
        $add('Account number', $method->detail('account_number'));
        $add('IFSC', $method->detail('ifsc'));
        $add('IBAN', $method->detail('iban'));
        $add('SWIFT / BIC', $method->detail('swift'));
        $add('Routing number', $method->detail('routing_number'));
        $add('Sort code', $method->detail('sort_code'));
        $add('ABA', $method->detail('aba'));
        $add('Intermediary bank', $method->detail('intermediary_bank'));
        $add('UPI ID', $method->detail('upi_id'));
        $add('Pay with', $method->detail('payment_provider'));
        $add('Payment link', $method->detail('payment_link'));
        $add('Currency', $method->currency);
        $add('Note', $method->detail('notes'));

        return ['label' => $method->label, 'international' => $method->isInternational(), 'lines' => $lines,
            'upi' => $method->detail('upi_id'), 'link' => $method->detail('payment_link')];
    }

    /** Freeze the parties and payment details into the invoice. Called when it is sent. */
    public function snapshot(Invoice $invoice): array
    {
        return ['parties' => $this->parties($invoice), 'payment' => $this->payment($invoice), 'taken_at' => now()->toIso8601String()];
    }

    /**
     * Things worth a second look before sending. None of these block sending (except an empty
     * invoice, handled elsewhere): they are prompts, because tax and invoicing rules vary by
     * country and by whether the issuer is registered.
     *
     * @return array<int, string>
     */
    public function warnings(Invoice $invoice): array
    {
        $invoice->loadMissing('freelancer.freelancerProfile', 'organization', 'client', 'payoutMethod', 'items');
        $profile = $invoice->freelancer->freelancerProfile;
        $parties = $this->parties($invoice);
        $w = [];

        if ($invoice->items->isEmpty() || $invoice->subtotal_minor <= 0) {
            $w[] = 'The invoice has no billable lines yet.';
        }
        if (! $profile?->country_code) {
            $w[] = 'The freelancer has not set a country, so tax and numbering rules cannot be matched. Add it in the profile.';
        }
        if (! $profile?->address_line1) {
            $w[] = 'The freelancer has no address on their profile. Most countries expect the supplier\'s address on an invoice.';
        }
        if (empty($parties['bill_to']['address']) && empty($parties['bill_to']['country'])) {
            $w[] = 'The "Bill to" party has no address or country. Fill it in on the '.($invoice->bill_to_type === 'client' ? 'client' : 'company').' profile.';
        }

        $treatment = $invoice->tax_treatment;
        $gstin = $parties['from']['tax_map']['gstin'] ?? null;
        if (in_array($treatment, ['gst_intra', 'gst_inter'], true)) {
            if (! $gstin) {
                $w[] = 'GST is being charged but the freelancer has no GSTIN on their profile. Only GST-registered suppliers charge GST.';
            }
            if (! $invoice->place_of_supply) {
                $w[] = 'Add the place of supply: it decides between CGST + SGST and IGST.';
            }
            if (! $invoice->sac_code) {
                $w[] = 'Add the SAC (service accounting code) for the service.';
            }
        }
        if ($treatment === 'export_lut' && ! $invoice->lut_reference) {
            $w[] = 'Zero-rated export under LUT: add the LUT reference.';
        }
        if ($invoice->isInternational() && $invoice->currency !== 'INR' && ! ((float) $invoice->exchange_rate > 0) && ($profile?->country_code === 'IN')) {
            $w[] = 'Add an exchange rate so the INR equivalent can be shown.';
        }
        if ($invoice->isInternational() && in_array($treatment, ['gst_intra', 'gst_inter'], true)) {
            $w[] = 'This is an international invoice with a domestic GST treatment. Check the tax treatment.';
        }
        if (! $invoice->payout_method_id) {
            $w[] = 'No payment profile is selected, so the invoice will not say how to pay.';
        } elseif ($invoice->payoutMethod && $invoice->payoutMethod->currency !== $invoice->currency) {
            $w[] = 'The payment profile is in '.$invoice->payoutMethod->currency.' but the invoice is in '.$invoice->currency.'.';
        }
        if ($invoice->due_date->lt($invoice->issue_date)) {
            $w[] = 'The due date is before the issue date.';
        }

        return $w;
    }

    private function freelancerBlock(User $user, ?FreelancerProfile $profile): array
    {
        $country = $profile?->country_code ?: $user->country_code;

        return [
            'name' => $user->name,
            'title' => $profile?->headline,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => collect([$profile?->address_line1, collect([$profile?->city, $profile?->state, $profile?->postal_code])->filter()->join(', ')])->filter()->all(),
            'country' => Countries::name($country),
            'country_code' => $country,
            'tax' => TaxFields::lines($country, $profile?->tax_ids),
            'tax_map' => $profile?->tax_ids ?? [],
            'code' => $profile?->freelancer_code,
            'website' => $profile?->website,
            'image_path' => $user->avatar_path,
        ];
    }

    private function companyBlock(Organization $org): array
    {
        return [
            'name' => $org->documentName(),
            'title' => $org->legal_name && $org->legal_name !== $org->name ? $org->name : null,
            'email' => $org->email,
            'phone' => $org->phone,
            'address' => collect([$org->address_line1, $org->address_line2, collect([$org->city, $org->state, $org->postal_code])->filter()->join(', ')])->filter()->all(),
            'country' => Countries::name($org->country_code),
            'country_code' => $org->country_code,
            'tax' => TaxFields::lines($org->country_code, $org->tax_ids),
            'tax_map' => $org->tax_ids ?? [],
            'website' => $org->website,
            'image_path' => $org->logo_path,
        ];
    }

    private function clientBlock(Client $client): array
    {
        return [
            'name' => $client->documentName(),
            'title' => $client->contact_name ? 'Attn: '.$client->contact_name : null,
            'email' => $client->email,
            'phone' => $client->phone,
            'address' => collect([$client->address, collect([$client->city, $client->state, $client->postal_code])->filter()->join(', ')])->filter()->all(),
            'country' => Countries::name($client->country_code),
            'country_code' => $client->country_code,
            'tax' => TaxFields::lines($client->country_code, $client->tax_ids),
            'tax_map' => $client->tax_ids ?? [],
            'website' => null,
            'image_path' => $client->logo_path,
        ];
    }

    /** A scannable code for the payer: a UPI payment request for INR, otherwise the payment link. */
    private function qr(Invoice $invoice, ?array $payment): ?string
    {
        $data = null;

        if ($payment && $payment['upi'] && $invoice->currency === 'INR') {
            $balance = $invoice->outstandingMinor() / 100;
            $data = 'upi://pay?'.http_build_query(array_filter([
                'pa' => $payment['upi'],
                'pn' => $invoice->freelancer->name,
                'am' => $balance > 0 ? number_format($balance, 2, '.', '') : null,
                'cu' => 'INR',
                'tn' => $invoice->number,
            ]), '', '&', PHP_QUERY_RFC3986);
        } elseif ($payment && $payment['link']) {
            $data = $payment['link'];
        }

        if (! $data) {
            return null;
        }

        return (new QRCode(new QROptions(['outputInterface' => QRGdImagePNG::class, 'outputBase64' => true, 'scale' => 5, 'quietzoneSize' => 1])))->render($data);
    }
}
