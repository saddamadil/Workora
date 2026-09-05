<?php

namespace App\Support;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Holds the organization the current request is acting inside.
 *
 * Resolved once, in SetCurrentOrganization middleware. Everything downstream —
 * the Eloquent global scope, the Postgres session variable, the policies —
 * reads from here. Nothing else should decide which tenant is current.
 */
class Tenancy
{
    private ?Organization $organization = null;

    private ?OrganizationMember $membership = null;

    /** True while running a job or command that legitimately crosses tenants. */
    private bool $unscoped = false;

    public function set(Organization $organization, ?OrganizationMember $membership = null): void
    {
        $this->organization = $organization;
        $this->membership = $membership;

        // Drives the Postgres RLS policy. Session-scoped, so it must be reset
        // on every request and every queued job.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT set_config('app.current_organization_id', ?, false)", [$organization->id]);
        }
    }

    public function clear(): void
    {
        $this->organization = null;
        $this->membership = null;

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT set_config('app.current_organization_id', '', false)");
        }
    }

    public function check(): bool
    {
        return $this->organization !== null;
    }

    public function organization(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?string
    {
        return $this->organization?->id;
    }

    public function idOrFail(): string
    {
        return $this->id() ?? throw new RuntimeException(
            'No current organization. A tenant-scoped model was touched outside a tenant context.'
        );
    }

    public function membership(): ?OrganizationMember
    {
        return $this->membership;
    }

    public function role(): ?OrganizationRole
    {
        return $this->membership?->role;
    }

    public function isUnscoped(): bool
    {
        return $this->unscoped;
    }

    /**
     * Run a callback with the tenant scope switched off.
     *
     * Only for platform admin work and cross-tenant maintenance jobs. Every call
     * site should be obvious in a code review — if you find yourself reaching for
     * this inside a controller, the controller is wrong.
     */
    public function withoutScope(callable $callback): mixed
    {
        $previous = $this->unscoped;
        $this->unscoped = true;

        try {
            return $callback();
        } finally {
            $this->unscoped = $previous;
        }
    }

    /** Run a callback as if the request belonged to a given organization. */
    public function forOrganization(Organization $organization, callable $callback): mixed
    {
        $previousOrg = $this->organization;
        $previousMember = $this->membership;

        $this->set($organization);

        try {
            return $callback();
        } finally {
            if ($previousOrg) {
                $this->set($previousOrg, $previousMember);
            } else {
                $this->clear();
            }
        }
    }
}
