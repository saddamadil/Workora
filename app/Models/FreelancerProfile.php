<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One profile per freelancer, shared across every company they work with.
 * Deliberately not tenant-scoped: the profile belongs to the person, and the
 * per-company rate and category live on OrganizationMember instead.
 */
class FreelancerProfile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id', 'headline', 'bio', 'years_experience',
        'default_hourly_rate_minor', 'default_currency', 'availability',
        'portfolio_url', 'resume_path', 'tax_identifier', 'is_public',
    ];

    protected function casts(): array
    {
        return [
            'years_experience' => 'decimal:1',
            'default_hourly_rate_minor' => 'integer',
            'is_public' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'freelancer_skills')
            ->withPivot('level')
            ->withTimestamps();
    }

    public function isAvailable(): bool
    {
        return $this->availability === 'available';
    }
}
