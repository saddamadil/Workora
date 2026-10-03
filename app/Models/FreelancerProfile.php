<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

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
        'address_line1', 'city', 'state', 'postal_code', 'country_code', 'tax_ids', 'website', 'linkedin_url',
        'freelancer_code', 'signature_path', 'invoice_prefix',
    ];

    protected function casts(): array
    {
        return [
            'years_experience' => 'decimal:1',
            'default_hourly_rate_minor' => 'integer',
            'is_public' => 'boolean',
            'tax_ids' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // A short public ID printed on invoices ("Freelancer ID"). Random so it reveals nothing.
        static::creating(function (self $profile) {
            if (! $profile->freelancer_code) {
                do {
                    $code = 'FL'.strtoupper(Str::random(6));
                } while (static::where('freelancer_code', $code)->exists());
                $profile->freelancer_code = $code;
            }
        });
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
