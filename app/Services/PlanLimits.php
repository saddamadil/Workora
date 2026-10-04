<?php

namespace App\Services;

use App\Models\Client;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\Project;
use App\Support\Tenancy;
use Illuminate\Validation\ValidationException;

/** What the current workspace's plan allows. No plan, or a plan with no cap, means unlimited. */
class PlanLimits
{
    public const LABELS = ['clients' => 'clients', 'projects' => 'projects', 'storage_mb' => 'MB of storage', 'team_members' => 'team members'];

    public function __construct(private Tenancy $tenancy) {}

    public function plan(): ?Plan
    {
        $orgId = $this->tenancy->id();
        if (! $orgId) {
            return null;
        }

        return \App\Models\Subscription::withoutGlobalScopes()->with('plan')->where('organization_id', $orgId)->latest('id')->first()?->plan;
    }

    public function limit(string $key): ?int
    {
        $plan = $this->plan();

        return $plan?->limit($key);
    }

    public function usage(): array
    {
        return [
            'clients' => Client::query()->count(),
            'projects' => Project::query()->count(),
            'storage_mb' => (int) ceil(\App\Models\File::query()->sum('size_bytes') / 1048576),
            'team_members' => OrganizationMember::query()->whereNotIn('role', ['client', 'client_member'])->where('status', 'active')->count(),
        ];
    }

    /** Throws a friendly validation error when adding one more would pass the plan's cap. */
    public function ensureRoomFor(string $key, int $adding = 1): void
    {
        $cap = $this->limit($key);
        if ($cap === null) {
            return;
        }
        if ($this->usage()[$key] + $adding > $cap) {
            throw ValidationException::withMessages(['plan' => "Your {$this->plan()->name} plan includes {$cap} ".self::LABELS[$key].'. Upgrade your plan to add more.']);
        }
    }
}
