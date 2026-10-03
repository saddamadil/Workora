<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\FreelancerProfile;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use App\Services\Workspaces;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class WorkoraTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Same strictness as local development: lazy loading, missing attributes and
        // non-fillable writes all throw, so N+1 queries and typos fail here, not in production.
        Model::shouldBeStrict(true);

        $this->withoutVite();
        Storage::fake(config('workora.disk'));
    }

    /** A company owner with a fresh company. */
    protected function userWithWorkspace(string $name = 'Alice', string $role = 'owner'): User
    {
        $user = User::create(['name' => $name, 'email' => strtolower($name).'@example.com', 'password' => 'secret-pass-1']);
        app(Workspaces::class)->createFor($user, $name.' Co');

        if ($role !== 'owner') {
            $user->memberships()->update(['role' => $role]);
        }

        return $user->refresh();
    }

    /** Put a person into someone else's company with a role. */
    protected function joinCompany(User $owner, string $name, string $role, int $rateMinor = 50000): User
    {
        $user = User::create(['name' => $name, 'email' => strtolower($name).'@example.com', 'password' => 'secret-pass-1']);
        $isFreelancer = $role === 'freelancer';

        if ($isFreelancer) {
            FreelancerProfile::create(['user_id' => $user->id]);
        }

        OrganizationMember::withoutGlobalScopes()->create([
            'organization_id' => $this->orgOf($owner)->id,
            'user_id' => $user->id,
            'role' => $role,
            'member_type' => $isFreelancer ? 'freelancer' : 'employee',
            'status' => 'active',
            'default_rate_minor' => $isFreelancer ? $rateMinor : null,
            'joined_at' => now(),
        ]);

        return $user->refresh();
    }

    protected function freelancer(User $owner, string $name = 'Fiona', int $rateMinor = 50000): User
    {
        return $this->joinCompany($owner, $name, 'freelancer', $rateMinor);
    }

    protected function orgOf(User $user): Organization
    {
        return Organization::findOrFail($user->memberships()->firstOrFail()->organization_id);
    }

    /** Run a callback as if inside the user's company, for setting up and checking data. */
    protected function inTenant(User $user, callable $fn): mixed
    {
        return app(Tenancy::class)->forOrganization($this->orgOf($user), $fn);
    }

    protected function projectFor(User $owner, array $members = [], array $attrs = []): Project
    {
        return $this->inTenant($owner, function () use ($owner, $members, $attrs) {
            $project = Project::create($attrs + ['name' => 'Website', 'slug' => 'website-'.uniqid(), 'status' => 'active', 'currency' => 'INR', 'budget_minor' => 5000000, 'created_by' => $owner->id]);
            foreach (array_merge([$owner], $members) as $u) {
                ProjectMember::create(['project_id' => $project->id, 'user_id' => $u->id, 'role_in_project' => 'member', 'can_view_budget' => $u->id === $owner->id]);
            }

            return $project;
        });
    }

    protected function taskFor(User $owner, Project $project, array $assignees = [], array $attrs = []): Task
    {
        return $this->inTenant($owner, function () use ($project, $assignees, $attrs, $owner) {
            $task = $project->tasks()->create($attrs + ['title' => 'Build homepage', 'status' => 'backlog', 'priority' => 'medium', 'created_by' => $owner->id, 'currency' => 'INR']);
            $task->syncAssignees(array_map(fn ($u) => $u->id, $assignees));

            return $task->refresh();
        });
    }

    protected function activeContract(User $owner, User $freelancer, array $attrs = []): Contract
    {
        return $this->inTenant($owner, fn () => Contract::create($attrs + [
            'user_id' => $freelancer->id, 'reference' => 'CT-'.uniqid(), 'title' => 'Retainer work', 'type' => 'hourly',
            'currency' => 'INR', 'hourly_rate_minor' => 60000, 'payment_cycle' => 'monthly', 'payment_terms_days' => 15,
            'starts_on' => now()->subMonth(), 'status' => 'active', 'created_by' => $owner->id, 'accepted_at' => now(),
        ]));
    }
}
