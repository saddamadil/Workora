<?php

namespace App\Http\Controllers;

use App\Models\FreelancerProfile;
use App\Models\Invitation;
use App\Models\OrganizationMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Reached from an emailed link, so the visitor may not be signed in yet. */
class InvitationController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $invitation = $this->find($token);
        $request->session()->put('invite_token', $token);

        $user = $request->user();

        return view('invite.show', [
            'invitation' => $invitation,
            'organization' => $invitation->organization,
            'usable' => $invitation->isPending(),
            'emailMatches' => $user && strcasecmp($user->email, $invitation->email) === 0,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->find($token);
        $user = $request->user();

        abort_unless($invitation->isPending(), 410, 'This invitation has expired or was already used.');
        abort_unless(strcasecmp($user->email, $invitation->email) === 0, 403, 'This invitation was sent to a different email address.');

        // An invitation never changes who someone already is in this workspace.
        $already = OrganizationMember::withoutGlobalScopes()->where('organization_id', $invitation->organization_id)->where('user_id', $user->id)->first();
        abort_if($already && $already->role->value !== $invitation->role, 422, 'You already have a different role in this workspace.');

        OrganizationMember::withoutGlobalScopes()->updateOrCreate(
            ['organization_id' => $invitation->organization_id, 'user_id' => $user->id],
            [
                'client_id' => $invitation->client_id,
                'role' => $invitation->role,
                'member_type' => $invitation->member_type,
                'status' => 'active',
                'invited_by' => $invitation->invited_by,
                'joined_at' => now(),
            ],
        );

        if ($invitation->member_type === 'freelancer') {
            FreelancerProfile::firstOrCreate(['user_id' => $user->id]);
        }

        $invitation->forceFill(['accepted_at' => now()])->save();

        $request->session()->forget('invite_token');
        $request->session()->put('current_organization_id', $invitation->organization_id);

        return redirect()->route(in_array($invitation->role, ['client', 'client_member'], true) ? 'portal.dashboard' : 'dashboard')->with('status', 'You joined '.$invitation->organization->name.'.');
    }

    private function find(string $token): Invitation
    {
        return Invitation::withoutGlobalScopes()->with('organization')->where('token', $token)->firstOrFail();
    }
}
