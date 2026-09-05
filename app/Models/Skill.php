<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Skill extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::creating(fn (self $skill) => $skill->slug ??= Str::slug($skill->name));
    }

    public function freelancerProfiles(): BelongsToMany
    {
        return $this->belongsToMany(FreelancerProfile::class, 'freelancer_skills');
    }
}
