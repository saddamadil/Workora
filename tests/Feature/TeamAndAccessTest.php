<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\OrganizationMember;
use App\Models\PayoutMethod;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkRequest;

class TeamAndAccessTest extends WorkoraTestCase
{
    public function test_inviting_a_freelancer_who_signs_up_from_the_link(): void
    {
        $owner = $this->userWithWorkspace('Olive');

        $this->actingAs($owner)->post(route('team.invite'), ['email' => 'New.Person@Example.com', 'kind' => 'freelancer'])
            ->assertRedirect()->assertSessionHas('status');
        $inv = Invitation::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('new.person@example.com', $inv->email);
        $this->assertSame('freelancer', $inv->role);
        auth()->logout();

        // Guest sees the invitation and is steered to sign up with the right email.
        $this->get(route('invite.show', $inv->token))->assertOk()->assertSee('Olive Co')->assertSee('Create an account');

        $this->post('/register', ['account_type' => 'freelancer', 'name' => 'New Person', 'email' => 'new.person@example.com', 'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1'])
            ->assertRedirect(route('invite.show', $inv->token));

        $this->post(route('invite.accept', $inv->token))->assertRedirect(route('dashboard'));
        $member = OrganizationMember::withoutGlobalScopes()->where('user_id', User::where('email', 'new.person@example.com')->value('id'))->firstOrFail();
        $this->assertSame('freelancer', $member->role->value);
        $this->assertSame('active', $member->status);
        $this->assertNotNull($inv->fresh()->accepted_at);

        $this->get(route('dashboard'))->assertOk();
        // The link cannot be used twice.
        $this->post(route('invite.accept', $inv->token))->assertStatus(410);
    }

    public function test_invitation_is_for_one_email_and_expires(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $this->actingAs($owner)->post(route('team.invite'), ['email' => 'intended@example.com', 'kind' => 'employee', 'role' => 'team_member']);
        $inv = Invitation::withoutGlobalScopes()->firstOrFail();

        $mallory = $this->userWithWorkspace('Mallory');
        $this->actingAs($mallory)->post(route('invite.accept', $inv->token))->assertForbidden();
        $this->actingAs($mallory)->get(route('invite.show', $inv->token))->assertOk()->assertSee('Sign out and use');

        $this->travel(15)->days();
        $this->get(route('invite.show', $inv->token))->assertOk()->assertSee('no longer valid');
    }

    public function test_cannot_invite_someone_already_in_the_company_and_only_admins_invite(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $member = $this->joinCompany($owner, 'Tom', 'team_member');

        $this->actingAs($owner)->post(route('team.invite'), ['email' => $member->email, 'kind' => 'employee'])->assertSessionHas('error');
        $this->actingAs($member)->post(route('team.invite'), ['email' => 'x@example.com', 'kind' => 'employee'])->assertForbidden();
        $this->actingAs($member)->get(route('team.index'))->assertOk();
        $this->actingAs($this->freelancer($owner))->get(route('team.index'))->assertForbidden();

        // An admin cannot mint an owner.
        $admin = $this->joinCompany($owner, 'Ada', 'admin');
        $this->actingAs($admin)->post(route('team.invite'), ['email' => 'boss@example.com', 'kind' => 'employee', 'role' => 'owner'])->assertForbidden();
    }

    public function test_role_changes_and_the_last_owner_is_protected(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $tom = $this->joinCompany($owner, 'Tom', 'team_member');
        $fiona = $this->freelancer($owner);
        $ownerMember = $owner->memberships()->firstOrFail();
        $tomMember = OrganizationMember::withoutGlobalScopes()->where('user_id', $tom->id)->firstOrFail();
        $fionaMember = OrganizationMember::withoutGlobalScopes()->where('user_id', $fiona->id)->firstOrFail();

        $this->actingAs($owner)->patch(route('team.update', $tomMember), ['role' => 'finance'])->assertRedirect();
        $this->assertSame('finance', $tomMember->fresh()->role->value);

        $this->actingAs($owner)->patch(route('team.update', $ownerMember), ['role' => 'admin'])->assertSessionHas('error');
        $this->assertSame('owner', $ownerMember->fresh()->role->value);

        // Freelancers keep their type and can have a default rate.
        $this->actingAs($owner)->patch(route('team.update', $fionaMember), ['role' => 'admin'])->assertStatus(422);
        $this->actingAs($owner)->patch(route('team.update', $fionaMember), ['default_rate' => '850', 'default_rate_currency' => 'INR'])->assertRedirect();
        $this->assertSame(85000, $fionaMember->fresh()->default_rate_minor);

        // Putting someone on hold keeps them out.
        $this->actingAs($owner)->patch(route('team.update', $tomMember), ['status' => 'inactive'])->assertRedirect();
        $this->actingAs($tom)->get(route('dashboard'))->assertRedirect(route('onboarding.index'));
    }

    public function test_one_person_in_two_companies_can_switch_but_not_into_a_third(): void
    {
        $alice = $this->userWithWorkspace('Alice');
        $bob = $this->userWithWorkspace('Bob');
        $carol = $this->userWithWorkspace('Carol');
        $fiona = $this->freelancer($alice);
        $this->joinCompanyAs($fiona, $bob);

        $aliceProject = $this->projectFor($alice, [$fiona], ['name' => 'Alice Project']);
        $bobProject = $this->projectFor($bob, [$fiona], ['name' => 'Bob Project']);

        $this->actingAs($fiona)->get(route('projects.index'))->assertOk();
        $this->actingAs($fiona)->post(route('organizations.switch', $this->orgOf($alice)->slug))->assertRedirect(route('dashboard'));
        $this->get(route('projects.index'))->assertSee('Alice Project')->assertDontSee('Bob Project');

        $this->post(route('organizations.switch', $this->orgOf($bob)->slug))->assertRedirect();
        $this->get(route('projects.index'))->assertSee('Bob Project')->assertDontSee('Alice Project');
        $this->get(route('projects.show', $aliceProject))->assertNotFound();

        $this->post(route('organizations.switch', $this->orgOf($carol)->slug))->assertForbidden();
    }

    private function joinCompanyAs(User $user, User $owner): void
    {
        OrganizationMember::withoutGlobalScopes()->create([
            'organization_id' => $this->orgOf($owner)->id, 'user_id' => $user->id, 'role' => 'freelancer',
            'member_type' => 'freelancer', 'status' => 'active', 'joined_at' => now(),
        ]);
    }

    public function test_every_main_screen_renders_for_every_role(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->freelancer($owner);
        $pm = $this->joinCompany($owner, 'Pia', 'project_manager');
        $finance = $this->joinCompany($owner, 'Finn', 'finance');
        $viewer = $this->joinCompany($owner, 'Vic', 'viewer');
        $project = $this->projectFor($owner, [$fiona, $pm]);
        $task = $this->taskFor($owner, $project, [$fiona]);
        $contract = $this->activeContract($owner, $fiona);

        $common = [route('dashboard'), route('projects.index'), route('tasks.index'), route('files.index'), route('shares.index'), route('profile.edit')];
        $byRole = [
            'owner' => [$owner, array_merge($common, [route('projects.show', $project), route('projects.create'), route('tasks.show', $task), route('team.index'), route('clients.index'), route('settings.company'), route('contracts.index'), route('contracts.show', $contract), route('contracts.create'), route('timesheets.index'), route('invoices.index'), route('payments.index'), route('time.index'), route('work-requests.index')])],
            'pm' => [$pm, array_merge($common, [route('projects.show', $project), route('tasks.show', $task), route('team.index'), route('clients.index'), route('timesheets.index'), route('time.index'), route('work-requests.index')])],
            'finance' => [$finance, array_merge($common, [route('contracts.index'), route('invoices.index'), route('payments.index'), route('team.index')])],
            'viewer' => [$viewer, array_merge($common, [route('projects.show', $project), route('team.index')])],
            'freelancer' => [$fiona, array_merge($common, [route('projects.show', $project), route('tasks.show', $task), route('time.index'), route('contracts.index'), route('contracts.show', $contract), route('invoices.index'), route('invoices.create'), route('payments.index'), route('work-requests.index'), route('work-requests.create')])],
        ];

        foreach ($byRole as $label => [$user, $urls]) {
            foreach ($urls as $url) {
                $this->actingAs($user)->get($url)->assertOk("{$label} could not open {$url}");
            }
        }

        // And the ones they must not reach.
        $this->actingAs($viewer)->get(route('time.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('projects.create'))->assertForbidden();
        $this->actingAs($finance)->get(route('timesheets.index'))->assertForbidden();
        $this->actingAs($finance)->get(route('settings.company'))->assertForbidden();
        $this->actingAs($fiona)->get(route('clients.index'))->assertForbidden();
        $this->actingAs($fiona)->get(route('settings.company'))->assertForbidden();
        $this->actingAs($fiona)->get(route('timesheets.index'))->assertForbidden();
    }

    public function test_dashboards_show_role_appropriate_numbers(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->freelancer($owner);
        $project = $this->projectFor($owner, [$fiona]);
        $this->taskFor($owner, $project, [$fiona], ['title' => 'Ship it', 'due_at' => now()->addDays(2)]);

        $this->actingAs($fiona)->get(route('dashboard'))->assertOk()->assertSee('Your open tasks')->assertSee('Ship it')->assertSee('Hours this week');
        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertSee('Active projects')->assertSee('Ship it')->assertSee('Invoices to approve');
    }

    public function test_proposals_negotiate_then_become_a_task(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->freelancer($owner);
        $gus = $this->freelancer($owner, 'Gus');
        $project = $this->projectFor($owner);

        $this->actingAs($fiona)->post(route('work-requests.store'), ['title' => 'Speed up checkout', 'description' => 'Audit and fix.', 'amount' => '8000', 'estimated_hours' => 10])->assertRedirect();
        $wr = WorkRequest::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(800000, $wr->proposed_amount_minor);

        $this->actingAs($gus)->get(route('work-requests.show', $wr))->assertNotFound();
        $this->actingAs($fiona)->post(route('work-requests.approve', $wr), ['project_id' => $project->id])->assertForbidden();

        $this->actingAs($owner)->post(route('work-requests.message', $wr), ['body' => 'How about 6500?', 'amount' => '6500'])->assertRedirect();
        $current = fn () => $this->inTenant($owner, fn () => $wr->fresh()->currentAmountMinor());
        $this->assertSame(650000, $current());
        // The latest counter-offer wins, not the first.
        $this->travel(5)->minutes();
        $this->actingAs($fiona)->post(route('work-requests.message', $wr), ['body' => 'Meet in the middle?', 'amount' => '7000'])->assertRedirect();
        $this->assertSame(700000, $current());
        $this->travel(5)->minutes();
        $this->actingAs($owner)->post(route('work-requests.message', $wr), ['body' => 'Deal, 7000.'])->assertRedirect();
        $this->assertSame(700000, $current());

        $this->actingAs($owner)->post(route('work-requests.approve', $wr), ['project_id' => $project->id])->assertRedirect();
        $task = Task::withoutGlobalScopes()->where('title', 'Speed up checkout')->firstOrFail();
        $this->assertSame(700000, $task->budget_minor);
        $this->assertSame('assigned', $task->status);
        $this->assertSame($project->id, $task->project_id);
        $this->assertTrue(Project::withoutGlobalScopes()->find($project->id)->users()->where('users.id', $fiona->id)->exists(), 'the freelancer was added to the project');
        $this->assertSame('approved', $wr->fresh()->status);

        // Closed proposals take no more messages.
        $this->actingAs($owner)->post(route('work-requests.message', $wr), ['body' => 'late'])->assertStatus(422);
        $this->actingAs($fiona)->get(route('tasks.show', $task))->assertOk();
    }

    public function test_profile_and_password(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->freelancer($owner);

        $this->actingAs($fiona)->post(route('profile.update'), [
            'name' => 'Fiona F', 'timezone' => 'Asia/Kolkata', 'headline' => 'Designer', 'hourly_rate' => '700', 'default_currency' => 'INR', 'availability' => 'limited',
            'payout_type' => 'upi', 'payout_label' => 'fiona@okbank', 'payout_notes' => 'Mornings only',
        ])->assertRedirect();
        $this->assertSame('Fiona F', $fiona->fresh()->name);
        $this->assertSame(70000, $fiona->freelancerProfile->default_hourly_rate_minor);
        $payout = PayoutMethod::where('user_id', $fiona->id)->firstOrFail();
        $this->assertSame('fiona@okbank', $payout->label);
        $this->assertNotSame('Mornings only', $payout->getRawOriginal('details_encrypted'), 'payout notes are stored encrypted');

        $this->actingAs($fiona)->post(route('profile.password'), ['current_password' => 'wrong', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])->assertSessionHasErrors('current_password');
        $this->actingAs($fiona)->post(route('profile.password'), ['current_password' => 'secret-pass-1', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])->assertRedirect();
        $this->post('/logout');
        $this->post('/login', ['email' => $fiona->email, 'password' => 'brand-new-pass'])->assertRedirect(route('dashboard'));
    }

    public function test_company_settings_save(): void
    {
        $owner = $this->userWithWorkspace('Olive');

        $this->actingAs($owner)->post(route('settings.company.update'), ['name' => 'Olive Studio', 'base_currency' => 'USD', 'timezone' => 'UTC', 'default_tax_rate' => 12.5, 'tax_identifier' => 'GSTIN1'])->assertRedirect();
        $org = $this->orgOf($owner)->fresh();
        $this->assertSame('Olive Studio', $org->name);
        $this->assertSame('USD', $org->base_currency);
    }
}
