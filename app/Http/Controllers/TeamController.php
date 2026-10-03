<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Models\Invitation;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Support\Money;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('staff');

        $members = OrganizationMember::query()->with('user.freelancerProfile')
            ->when($request->query('type') === 'freelancers', fn ($q) => $q->where('member_type', 'freelancer'))
            ->when($request->query('type') === 'staff', fn ($q) => $q->where('member_type', 'employee'))
            ->orderBy('member_type')->orderBy('created_at')->get();

        return view('team.index', [
            'members' => $members,
            'invitations' => Invitation::query()->whereNull('accepted_at')->where('expires_at', '>', now())->latest()->get(),
            'type' => $request->query('type'),
            'roles' => OrganizationRole::staffRoles(),
        ]);
    }

    public function invite(Request $request, Tenancy $tenancy): RedirectResponse
    {
        $this->authorize('manage-team');

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'kind' => ['required', 'in:freelancer,employee'],
            'role' => ['nullable', Rule::in(array_map(fn ($r) => $r->value, OrganizationRole::staffRoles()))],
        ]);

        $isFreelancer = $data['kind'] === 'freelancer';
        $role = $isFreelancer ? 'freelancer' : ($data['role'] ?? 'team_member');

        // Only an owner can mint another owner.
        abort_if($role === 'owner' && $tenancy->role() !== OrganizationRole::Owner, 403);

        // Someone already in the company does not need an invitation.
        $existing = User::where('email', $data['email'])->first();
        if ($existing && OrganizationMember::where('user_id', $existing->id)->exists()) {
            return back()->with('error', $data['email'].' is already part of this company.');
        }

        $invitation = Invitation::create([
            'email' => strtolower($data['email']),
            'role' => $role,
            'member_type' => $data['kind'],
            'invited_by' => $request->user()->id,
        ]);

        $link = route('invite.show', $invitation->token);
        $company = $tenancy->organization()->name;

        try {
            Mail::raw("{$request->user()->name} invited you to join {$company} on Workora.\n\nOpen this link to accept:\n{$link}\n", fn ($m) => $m
                ->to($invitation->email)->subject("You're invited to {$company} on Workora"));
        } catch (\Throwable) {
            // Mail may not be configured yet; the link is shown below either way.
        }

        return back()->with('status', 'Invitation created. Share this link with them: '.$link);
    }

    public function revoke(Invitation $invitation): RedirectResponse
    {
        $this->authorize('manage-team');
        $invitation->delete();

        return back()->with('status', 'Invitation cancelled.');
    }

    public function update(Request $request, OrganizationMember $member, Tenancy $tenancy): RedirectResponse
    {
        $this->authorize('manage-team');

        $data = $request->validate([
            'role' => ['sometimes', Rule::in(array_map(fn ($r) => $r->value, OrganizationRole::cases()))],
            'status' => ['sometimes', 'in:active,on_hold,inactive'],
            'default_rate' => ['nullable', 'numeric', 'min:0'],
            'default_rate_currency' => ['nullable', Rule::in(array_keys(Money::CURRENCIES))],
        ]);

        // The last owner cannot be demoted or switched off, or the company is locked out.
        if ($member->role === OrganizationRole::Owner
            && (($data['role'] ?? 'owner') !== 'owner' || ($data['status'] ?? 'active') !== 'active')
            && OrganizationMember::where('role', 'owner')->where('status', 'active')->count() <= 1) {
            return back()->with('error', 'A company needs at least one active owner.');
        }

        abort_if(($data['role'] ?? null) === 'owner' && $tenancy->role() !== OrganizationRole::Owner, 403);

        // Freelancers stay freelancers; staff cannot be turned into one by mistake.
        if (isset($data['role'])) {
            abort_if($member->isFreelancer() !== ($data['role'] === 'freelancer'), 422, 'Role does not match member type.');
        }

        $member->update(array_filter([
            'role' => $data['role'] ?? null,
            'status' => $data['status'] ?? null,
            'default_rate_minor' => array_key_exists('default_rate', $data) ? Money::toMinor($data['default_rate']) : null,
            'default_rate_currency' => $data['default_rate_currency'] ?? null,
        ], fn ($v) => $v !== null));

        return back()->with('status', 'Saved.');
    }
}
