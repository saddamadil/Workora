<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Support\Tenancy;

/**
 * Authorization for projects.
 *
 * Two independent gates, both of which must pass:
 *   1. Tenant  — enforced by the global scope and RLS, not here.
 *   2. Role + project membership — enforced here.
 *
 * Freelancers are deliberately handled as a separate branch rather than as a
 * weaker staff role, because their access is always project-scoped and they can
 * never see budgets, other freelancers' rates, or company-wide finance.
 */
class ProjectPolicy
{
    public function __construct(private Tenancy $tenancy) {}

    public function viewAny(User $user): bool
    {
        return $this->tenancy->role() !== null;
    }

    public function view(User $user, Project $project): bool
    {
        $role = $this->tenancy->role();

        if ($role === null) {
            return false;
        }

        if ($role->seesAllProjects()) {
            return true;
        }

        return $project->hasMember($user);
    }

    public function create(User $user): bool
    {
        $role = $this->tenancy->role();

        return $role !== null
            && in_array($role->value, ['owner', 'admin', 'project_manager'], true);
    }

    public function update(User $user, Project $project): bool
    {
        $role = $this->tenancy->role();

        if ($role === null || $role->isFreelancer()) {
            return false;
        }

        if (in_array($role->value, ['owner', 'admin'], true)) {
            return true;
        }

        return $role->value === 'project_manager'
            && $project->project_manager_id === $user->id;
    }

    public function delete(User $user, Project $project): bool
    {
        return in_array($this->tenancy->role()?->value, ['owner', 'admin'], true);
    }

    /** Budget, spend to date, and freelancer rates on this project. */
    public function viewFinancials(User $user, Project $project): bool
    {
        $role = $this->tenancy->role();

        if ($role === null || $role->isFreelancer()) {
            return false;
        }

        if ($role->seesMoney()) {
            return true;
        }

        // A project manager sees the budget only if explicitly granted.
        return $project->members()
            ->where('user_id', $user->id)
            ->where('can_view_budget', true)
            ->exists();
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    /** Approving submitted deliverables on this project. */
    public function approveWork(User $user, Project $project): bool
    {
        $role = $this->tenancy->role();

        return $role !== null
            && $role->canApproveWork()
            && $this->view($user, $project);
    }
}
