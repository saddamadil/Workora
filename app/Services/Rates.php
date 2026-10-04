<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Models\User;

/** Which hourly rate applies when a person logs time. Fixed at logging time so later rate changes do not rewrite history. */
class Rates
{
    public function hourlyFor(User $user, ?Project $project = null): ?int
    {
        $contract = $this->activeHourlyContract($user, $project);

        if ($contract?->hourly_rate_minor) {
            return $contract->hourly_rate_minor;
        }

        return OrganizationMember::query()->where('user_id', $user->id)->value('default_rate_minor')
            ?: \App\Models\FreelancerProfile::query()->where('user_id', $user->id)->value('default_hourly_rate_minor');
    }

    /** The contract that governs this person's hourly work, preferring one tied to the project. */
    public function activeHourlyContract(User $user, ?Project $project = null): ?Contract
    {
        return Contract::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereIn('type', ['hourly', 'retainer'])
            ->whereDate('starts_on', '<=', now())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', now()))
            ->when($project, fn ($q) => $q->where(fn ($q) => $q->whereNull('project_id')->orWhere('project_id', $project->id)))
            ->orderByRaw('project_id is null')
            ->first();
    }
}
