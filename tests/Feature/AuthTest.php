<?php

namespace Tests\Feature;

use App\Models\OrganizationMember;
use App\Models\User;

class AuthTest extends WorkoraTestCase
{
    public function test_registering_creates_a_workspace_and_signs_in(): void
    {
        $this->post('/register', [
            'name' => 'Sam Lee', 'email' => 'sam@example.com',
            'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1',
        ])->assertRedirect(route('files.index'));

        $user = User::where('email', 'sam@example.com')->firstOrFail();
        $member = OrganizationMember::withoutGlobalScopes()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('owner', $member->role->value);
        $this->assertSame('active', $member->status);
        $this->assertAuthenticatedAs($user);
        $this->get(route('files.index'))->assertOk()->assertSee('Sam Lee');
    }

    public function test_short_passwords_are_rejected(): void
    {
        $this->post('/register', [
            'name' => 'Sam', 'email' => 'sam@example.com', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    public function test_login_and_logout(): void
    {
        $this->userWithWorkspace('Alice');

        $this->post('/login', ['email' => 'alice@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'alice@example.com', 'password' => 'secret-pass-1'])->assertRedirect(route('files.index'));
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
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
        $this->actingAs($user)->post(route('onboarding.store'), ['workspace' => 'Fresh'])->assertRedirect(route('files.index'));
        $this->actingAs($user)->get(route('files.index'))->assertOk();
    }
}
