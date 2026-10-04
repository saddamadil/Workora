<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\PayoutMethod;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Support\Money;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    /** Overview: how the team and its billing are doing, and what still needs setting up. */
    public function index(Request $request): View
    {
        $this->authorize('staff');
        $org = $this->tenancyOrg();

        $members = OrganizationMember::query()->where('status', 'active')->where('member_type', '!=', 'client')->get();
        $freelancerIds = $members->where('member_type', 'freelancer')->pluck('user_id');
        $withProfile = PayoutMethod::whereIn('user_id', $freelancerIds)->distinct()->pluck('user_id');

        $money = Gate::allows('see-money');
        $invoices = $money ? Invoice::query()->with('freelancer:id,name')->where('status', '!=', 'draft')->latest('issue_date')->limit(5)->get() : collect();

        return view('team.overview', [
            'counts' => [
                'staff' => $members->where('member_type', 'employee')->count(),
                'freelancers' => $freelancerIds->count(),
                'invitations' => Invitation::query()->whereNull('accepted_at')->where('expires_at', '>', now())->count(),
                'missingPayment' => $freelancerIds->diff($withProfile)->count(),
            ],
            'stats' => $money ? \App\Services\InvoiceStats::summarize(Invoice::query()->where('status', '!=', 'draft')->get()) : null,
            'invoices' => $invoices,
            'setup' => [
                ['Add your company logo, legal name and tax details', $org->logo_path && $org->legal_name, route('settings.company'), 'Company profile'],
                ['Invite your first freelancer', $freelancerIds->isNotEmpty(), route('team.invitations'), 'Invitations'],
                ['Every freelancer has a payment profile', $freelancerIds->isNotEmpty() && $freelancerIds->diff($withProfile)->isEmpty(), route('team.payment-profiles'), 'Payment profiles'],
                ['Add a client to bill', \App\Models\Client::query()->exists(), route('clients.create'), 'Clients'],
            ],
        ]);
    }

    public function members(Request $request): View
    {
        $isFreelancer = $this->tenancyIsFreelancer();
        abort_unless($isFreelancer || Gate::allows('staff'), 403);

        if ($isFreelancer) {
            return view('team.members-freelancer', [
                'companies' => $request->user()->organizations()->wherePivot('status', 'active')->get(),
                'current' => $this->tenancyOrg(),
            ]);
        }

        $type = $request->query('type');
        $members = OrganizationMember::query()->with('user.freelancerProfile')->where('member_type', '!=', 'client')
            ->when($type === 'freelancers', fn ($q) => $q->where('member_type', 'freelancer'))
            ->when($type === 'staff', fn ($q) => $q->where('member_type', 'employee'))
            ->when(trim((string) $request->query('q')) !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.addcslashes(trim($request->query('q')), '%_\\').'%')))
            ->orderBy('member_type')->orderBy('created_at')->get();

        $userIds = $members->pluck('user_id');
        $owed = Gate::allows('see-money')
            ? Invoice::query()->whereIn('user_id', $userIds)->whereIn('status', ['approved', 'partially_paid'])
                ->selectRaw('user_id, sum(total_minor - amount_paid_minor) as owed')->groupBy('user_id')->pluck('owed', 'user_id')
            : collect();
        $profiles = PayoutMethod::whereIn('user_id', $userIds)->distinct()->pluck('user_id')->flip();

        return view('team.members', [
            'members' => $members, 'owed' => $owed, 'profiles' => $profiles, 'type' => $type, 'search' => $request->query('q'),
            'roles' => OrganizationRole::staffRoles(),
        ]);
    }

    /** One person's profile, as the company sees it. */
    public function member(OrganizationMember $member): View
    {
        $this->authorize('staff');
        abort_if($member->role->isClient(), 404);
        $member->load('user.freelancerProfile');
        $profile = $member->user->freelancerProfile;
        $money = Gate::allows('see-money');

        return view('team.member', [
            'member' => $member,
            'profile' => $profile,
            'payment' => $money && $member->isFreelancer() ? PayoutMethod::where('user_id', $member->user_id)->orderByDesc('is_default')->get() : collect(),
            'invoices' => $money ? Invoice::query()->where('user_id', $member->user_id)->where('status', '!=', 'draft')->latest('issue_date')->limit(6)->get() : collect(),
            'canSeeDetails' => $money || Gate::allows('manage-team'),
        ]);
    }

    public function invitations(): View
    {
        $this->authorize('staff');

        return view('team.invitations', [
            'pending' => Invitation::query()->whereNull('accepted_at')->where('expires_at', '>', now())->latest()->get(),
            'history' => Invitation::query()->where(fn ($q) => $q->whereNotNull('accepted_at')->orWhere('expires_at', '<=', now()))->latest()->limit(20)->get(),
            'roles' => OrganizationRole::staffRoles(),
        ]);
    }

    public function roles(): View
    {
        $this->authorize('staff');

        return view('team.roles', [
            'matrix' => \App\Support\Permissions::matrix(),
            'roles' => array_values(array_filter(OrganizationRole::cases(), fn ($r) => ! $r->isClient())),
            'counts' => OrganizationMember::query()->where('status', 'active')->selectRaw('role, count(*) as n')->groupBy('role')->pluck('n', 'role'),
        ]);
    }

    private function tenancyOrg()
    {
        return app(Tenancy::class)->organization();
    }

    private function tenancyIsFreelancer(): bool
    {
        return app(Tenancy::class)->isFreelancer();
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
            \App\Support\BrandedMail::send($invitation->email, "You're invited to {$company} on Freelancy", "Join {$company}", "{$request->user()->name} invited you to join {$company} on Freelancy.", 'Accept the invitation', $link);
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
        abort_if($member->role->isClient(), 404);

        $data = $request->validate([
            'role' => ['sometimes', Rule::in(array_map(fn ($r) => $r->value, array_filter(OrganizationRole::cases(), fn ($r) => ! $r->isClient())))],
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
