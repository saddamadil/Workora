<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Str;

class Workspaces
{
    /** Create a workspace for a user and make them its owner. Mode 'solo' is a freelancer's own business. */
    public function createFor(User $user, string $name, string $mode = 'team'): Organization
    {
        $organization = Organization::create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'mode' => $mode,
        ]);

        OrganizationMember::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'member_type' => 'employee',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $organization;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;

        while (Organization::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }
}
