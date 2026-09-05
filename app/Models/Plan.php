<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name', 'slug', 'price_minor', 'currency', 'interval', 'limits', 'is_active', 'position',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'limits' => 'array',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** A named limit from the plan, or null when the plan does not cap it. */
    public function limit(string $key): ?int
    {
        return $this->limits[$key] ?? null;
    }
}
