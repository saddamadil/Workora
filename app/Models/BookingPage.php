<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A public page where anyone can pick a free slot. Not tenant-scoped on purpose: it is looked up by its public address. */
class BookingPage extends Model
{
    use HasUuids;

    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    protected $fillable = ['organization_id', 'user_id', 'slug', 'title', 'description', 'location', 'duration_minutes', 'buffer_minutes', 'notice_hours', 'window_days', 'timezone', 'hours', 'active'];

    protected function casts(): array
    {
        return ['hours' => 'array', 'active' => 'boolean', 'duration_minutes' => 'integer', 'buffer_minutes' => 'integer', 'notice_hours' => 'integer', 'window_days' => 'integer'];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function url(): string
    {
        return route('book.show', $this->slug);
    }
}
