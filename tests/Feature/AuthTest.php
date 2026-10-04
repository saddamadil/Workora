<?php

namespace Tests\Feature;

use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuthTest extends WorkoraTestCase
{
    public function test_registering_creates_a_workspace_and_signs_in(): void
    {
        $this->post('/register', [
            'account_type' => 'company', 'workspace' => 'Lee Studio', 'name' => 'Sam Lee', 'email' => 'sam@example.com',
            'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'sam@example.com')->firstOrFail();
        $member = OrganizationMember::withoutGlobalScopes()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('owner', $member->role->value);
        $this->assertSame('active', $member->status);
        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertOk()->assertSee('Lee Studio');
    }

    public function test_short_passwords_are_rejected(): void
    {
        $this->post('/register', [
            'account_type' => 'company', 'name' => 'Sam', 'email' => 'sam@example.com', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    public function test_login_and_logout(): void
    {
        $this->userWithWorkspace('Alice');

        $this->post('/login', ['email' => 'alice@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'alice@example.com', 'password' => 'secret-pass-1'])->assertRedirect(route('dashboard'));
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_freelancer_who_signs_up_gets_their_own_workspace(): void
    {
        $this->post('/register', [
            'account_type' => 'freelancer', 'name' => 'Fay Free', 'email' => 'fay@example.com',
            'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1',
        ])->assertRedirect(route('welcome'));

        $user = User::where('email', 'fay@example.com')->firstOrFail();
        $this->assertNotNull($user->freelancerProfile);
        $member = OrganizationMember::withoutGlobalScopes()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('owner', $member->role->value);
        $this->assertSame('solo', \App\Models\Organization::findOrFail($member->organization_id)->mode);

        $this->get(route('dashboard'))->assertOk()->assertSee('Good')->assertSee('Add client');
        $this->get(route('profile.edit'))->assertOk()->assertSee('Professional');
    }

    public function test_a_freelancer_invited_by_a_company_does_not_get_a_second_workspace(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $this->actingAs($owner)->post(route('team.invite'), ['email' => 'fay@example.com', 'kind' => 'freelancer'])->assertRedirect();
        $invitation = \App\Models\Invitation::withoutGlobalScopes()->firstOrFail();
        auth()->logout();

        $this->get(route('invite.show', $invitation->token))->assertOk();
        $this->post('/register', [
            'account_type' => 'freelancer', 'name' => 'Fay Free', 'email' => 'fay@example.com',
            'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1',
        ]);
        $user = User::where('email', 'fay@example.com')->firstOrFail();
        $this->assertSame(0, OrganizationMember::withoutGlobalScopes()->where('user_id', $user->id)->count());
        $this->assertNotNull($user->freelancerProfile);
    }

    public function test_files_require_sign_in(): void
    {
        $this->get(route('files.index'))->assertRedirect(route('login'));
    }

    public function test_user_without_a_workspace_is_sent_to_onboarding_and_can_create_one(): void
    {
        $user = User::create(['name' => 'Lone', 'email' => 'lone@example.com', 'password' => 'secret-pass-1']);

        $this->actingAs($user)->get(route('files.index'))->assertRedirect(route('onboarding.index'));
        $this->actingAs($user)->get(route('onboarding.index'))->assertOk();
        $this->actingAs($user)->post(route('onboarding.store'), ['workspace' => 'Fresh'])->assertRedirect(route('dashboard'));
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_suite_runs_with_strict_models(): void
    {
        // If this fails the whole suite has stopped catching lazy loading and typos.
        $this->assertTrue(Model::preventsLazyLoading());
        $this->assertTrue(Model::preventsAccessingMissingAttributes());
    }
}
