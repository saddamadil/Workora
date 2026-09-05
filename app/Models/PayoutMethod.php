<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where a freelancer wants to be paid.
 *
 * `details` is encrypted at rest and should hold a reference or a masked label
 * only — an account's last four digits, a UPI handle, a processor token. Full
 * card numbers and complete bank credentials are never stored here.
 */
class PayoutMethod extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id', 'type', 'label', 'currency',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
