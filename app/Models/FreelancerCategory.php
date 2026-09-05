<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FreelancerCategory extends Model
{
    use BelongsToOrganization, HasFactory, HasUuids;

    protected $fillable = ['organization_id', 'name', 'colour'];

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class, 'freelancer_category_id');
    }
}
