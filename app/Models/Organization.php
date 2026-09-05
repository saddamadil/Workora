<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The tenant. This model deliberately does NOT use BelongsToOrganization —
 * it is the thing being scoped to, not a thing that is scoped.
 */
class Organization extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'logo_path', 'website', 'industry',
        'address_line1', 'address_line2', 'city', 'state', 'postal_code',
        'country_code', 'tax_identifier', 'default_tax_rate',
        'base_currency', 'timezone', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'default_tax_rate' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class)->withoutGlobalScopes();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members')
            ->withPivot(['role', 'member_type', 'status']);
    }

    /** Members of this company who are freelancers rather than staff. */
    public function freelancers(): HasMany
    {
        return $this->members()->where('member_type', 'freelancer');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
