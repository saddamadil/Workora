<?php

namespace App\Http\Middleware;

use App\Models\OrganizationMember;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves which company this request is acting inside, and proves the signed-in
 * user is a member of it before letting anything else run.
 *
 * A freelancer typically belongs to several companies, so the current one comes
 * from the session and is re-verified on every request rather than trusted.
 */
class SetCurrentOrganization
{
    public function __construct(private Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $organizationId = $request->session()->get('current_organization_id');

        $membership = OrganizationMember::withoutGlobalScopes()
            ->with('organization')
            ->where('user_id', $user->id)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('status', 'active')
            ->orderBy('created_at')
            ->first();

        if (! $membership) {
            $this->tenancy->clear();

            // Signed in but with no active company: send them to onboarding or
            // to the pending-invitation screen rather than a half-scoped page.
            return redirect()->route('onboarding.index');
        }

        $this->tenancy->set($membership->organization, $membership);
        $request->session()->put('current_organization_id', $membership->organization_id);

        $response = $next($request);

        $this->tenancy->clear();

        return $response;
    }
}
