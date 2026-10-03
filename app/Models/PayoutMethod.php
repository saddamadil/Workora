<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment profile: where a freelancer wants to be paid.
 *
 * These are the receiving details that appear on an invoice (account number,
 * IFSC, IBAN, SWIFT, UPI ID, payment link), stored encrypted at rest and shown in
 * lists only as a masked tail. They are never a login: passwords, PINs, OTPs and
 * card numbers or CVVs are not collected and must not be put in any field.
 */
class PayoutMethod extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id', 'kind', 'country_code', 'type', 'label', 'currency',
        'details_encrypted', 'processor', 'processor_reference', 'is_default',
    ];

    protected $hidden = ['details_encrypted'];

    protected function casts(): array
    {
        return [
            'details_encrypted' => 'encrypted:array',
            'is_default' => 'boolean',
        ];
    }

    public const DOMESTIC = 'domestic';

    public const INTERNATIONAL = 'international';

    /** Every key a profile may carry in its encrypted details. */
    public const DETAIL_KEYS = [
        'account_holder', 'bank_name', 'branch', 'bank_address', 'account_number', 'ifsc', 'upi_id',
        'iban', 'swift', 'routing_number', 'sort_code', 'aba', 'intermediary_bank', 'payment_provider', 'payment_link', 'notes',
    ];

    public function detail(string $key): ?string
    {
        $value = ($this->details_encrypted ?? [])[$key] ?? null;

        return $value === '' ? null : $value;
    }

    public function isInternational(): bool
    {
        return $this->kind === self::INTERNATIONAL;
    }

    /** "••••1234" for a number: enough to recognise the account, not enough to use it. */
    public static function mask(?string $value): string
    {
        $value = preg_replace('/\s+/', '', (string) $value);

        return $value === '' ? '' : '••••'.substr($value, -4);
    }

    /** One line for lists: "HDFC Bank ••••4321" or "UPI name@bank". */
    public function summary(): string
    {
        $parts = array_filter([
            $this->detail('bank_name') ?: $this->detail('payment_provider'),
            $this->detail('iban') ? self::mask($this->detail('iban')) : ($this->detail('account_number') ? self::mask($this->detail('account_number')) : null),
            $this->detail('upi_id') ? 'UPI '.$this->detail('upi_id') : null,
        ]);

        return $parts ? implode(' · ', $parts) : (string) $this->label;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
