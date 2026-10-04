<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $fillable = ['organization_id', 'booking_page_id', 'user_id', 'name', 'email', 'notes', 'starts_at', 'ends_at', 'status', 'token'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(BookingPage::class, 'booking_page_id');
    }
}
