<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Models\User;
use App\Services\Workspaces;

class ClientPortalTest extends WorkoraTestCase
{
    private function solo(string $name = 'Sam'): User
    {
        $user = User::create(['name' => $name, 'email' => strtolower($name).'@example.com', 'password' => 'secret-pass-1']);
        \App\Models\FreelancerProfile::create(['user_id' => $user->id, 'country_code' => 'IN', 'address_line1' => '1 MG Road', 'city' => 'Bengaluru']);
        app(Workspaces::class)->createFor($user, $name.' Studio', 'solo');

        return $user->refresh();
    }

    private function client(User $owner, string $name, string $email): Client
    {
        $this->actingAs($owner)->post(route('clients.store'), ['name' => $name, 'email' => $email, 'type' => 'company', 'default_currency' => 'INR'])->assertRedirect();

        return $this->inTenant($owner, fn () => Client::where('name', $name)->firstOrFail());
    }

    /** Invite the client's email and sign them in as that client. */
    private function portalUser(User $owner, Client $client, string $name): User
    {
        $this->actingAs($owner)->post(route('clients.invite', $client))->assertRedirect();
        $invitation = Invitation::withoutGlobalScopes()->where('client_id', $client->id)->firstOrFail();

        $user = User::create(['name' => $name, 'email' => $invitation->email, 'password' => 'secret-pass-1']);
        $this->actingAs($user)->post(route('invite.accept', $invitation->token))->assertRedirect(route('portal.dashboard'));

        return $user->refresh();
    }

    private function makeProject(Client $client, User $owner, string $name): Project
    {
        $this->actingAs($owner)->post(route('projects.store'), ['name' => $name, 'client_id' => $client->id, 'status' => 'active', 'currency' => 'INR'])->assertRedirect();

        return $this->inTenant($owner, fn () => Project::where('name', $name)->firstOrFail());
    }

    private function sentInvoice(User $owner, Client $client, string $rate = '1000'): Invoice
    {
        $before = Invoice::withoutGlobalScopes()->pluck('id')->all();
        $this->actingAs($owner)->post(route('invoices.store'), [
            'bill_to_type' => 'client', 'client_id' => $client->id, 'issue_date' => now()->toDateString(), 'terms_days' => '14',
            'currency' => 'INR', 'invoice_type' => 'domestic', 'template' => 'professional', 'tax_treatment' => 'none',
        ])->assertRedirect();
        $invoice = Invoice::withoutGlobalScopes()->whereNotIn('id', $before)->firstOrFail();
        $this->actingAs($owner)->post(route('invoices.items.add', $invoice), ['description' => 'Design', 'quantity' => 1, 'unit' => 'items', 'unit_rate' => $rate])->assertRedirect();

        return $invoice;
    }

    public function test_adding_the_same_client_twice_is_stopped(): void
    {
        $sam = $this->solo();
        $abc = $this->client($sam, 'ABC GmbH', 'abc@example.com');

        $this->actingAs($sam)->post(route('clients.store'), ['name' => 'Another name', 'email' => 'ABC@example.com'])->assertSessionHas('duplicate');
        $this->actingAs($sam)->post(route('clients.store'), ['name' => 'abc gmbh'])->assertSessionHas('duplicate');
        $this->assertSame(1, $this->inTenant($sam, fn () => Client::count()));
        $this->actingAs($sam)->get(route('clients.show', $abc))->assertOk()->assertSee('Invite client');
    }

    public function test_invitation_creates_a_client_login_and_connects_existing_accounts(): void
    {
        $sam = $this->solo();
        $abc = $this->client($sam, 'ABC GmbH', 'abc@example.com');

        // No email, no invitation.
        $nomail = $this->client($sam, 'No Mail', 'x@example.com');
        $this->inTenant($sam, fn () => $nomail->update(['email' => null]));
        $this->actingAs($sam)->post(route('clients.invite', $nomail))->assertStatus(422);

        // The client already has their own Freelancy account: it is connected, not duplicated.
        $existing = $this->solo('Abc');
        $this->inTenant($sam, fn () => $abc->update(['email' => $existing->email]));
        $this->actingAs($sam)->post(route('clients.invite', $abc))->assertRedirect();
        $inv = Invitation::withoutGlobalScopes()->where('client_id', $abc->id)->firstOrFail();
        $this->actingAs($existing)->post(route('invite.accept', $inv->token))->assertRedirect(route('portal.dashboard'));

        $this->assertSame(1, User::where('email', $existing->email)->count());
        $roles = OrganizationMember::withoutGlobalScopes()->where('user_id', $existing->id)->pluck('role')->map->value->sort()->values()->all();
        $this->assertSame(['client', 'owner'], $roles);
        $this->assertSame($abc->id, OrganizationMember::withoutGlobalScopes()->where('user_id', $existing->id)->where('role', 'client')->value('client_id'));

        // The same link cannot be used twice.
        $this->actingAs($existing)->post(route('invite.accept', $inv->token))->assertStatus(410);
    }

    public function test_a_team_member_email_cannot_become_a_client_login(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $this->joinCompany($owner, 'Pia', 'project_manager');
        $client = $this->inTenant($owner, fn () => Client::create(['name' => 'Pia Co', 'email' => 'pia@example.com']));

        $this->actingAs($owner)->post(route('clients.invite', $client))->assertSessionHas('error');
        $this->assertSame(0, Invitation::withoutGlobalScopes()->count());
    }

    public function test_a_client_sees_only_their_own_projects_and_nothing_else_of_the_workspace(): void
    {
        $sam = $this->solo();
        $abc = $this->client($sam, 'ABC GmbH', 'abc@example.com');
        $xyz = $this->client($sam, 'XYZ Ltd', 'xyz@example.com');
        $seo = $this->makeProject($abc, $sam, 'SEO Campaign');
        $secret = $this->makeProject($xyz, $sam, 'XYZ Secret Launch');

        $alice = $this->portalUser($sam, $abc, 'Alice');

        $this->actingAs($alice)->get(route('portal.dashboard'))->assertOk()->assertSee('Welcome, ABC GmbH')->assertSee('SEO Campaign')->assertDontSee('XYZ');
        $this->actingAs($alice)->get(route('portal.projects'))->assertOk()->assertSee('SEO Campaign')->assertDontSee('XYZ Secret Launch');
        $this->actingAs($alice)->get(route('portal.project', $seo))->assertOk()->assertSee('SEO Campaign');
        $this->actingAs($alice)->get(route('portal.project', $secret))->assertNotFound();

        // Every workspace route is closed to a client login.
        foreach (['dashboard' => 'portal.dashboard', 'projects.index' => null, 'clients.index' => null, 'tasks.index' => null, 'team.index' => null, 'invoices.index' => null, 'files.index' => null, 'settings.company' => null, 'payments.index' => null, 'time.index' => null] as $route => $_) {
            if ($route === 'dashboard') {
                continue;
            }
            $this->actingAs($alice)->get(route($route))->assertRedirect(route('portal.dashboard'));
        }
        $this->actingAs($alice)->get(route('clients.show', $xyz))->assertRedirect(route('portal.dashboard'));
        $this->actingAs($alice)->get(route('projects.show', $secret))->assertRedirect(route('portal.dashboard'));
        $this->actingAs($alice)->post(route('clients.store'), ['name' => 'Sneaky'])->assertRedirect(route('portal.dashboard'));
        $this->assertSame(2, $this->inTenant($sam, fn () => Client::count()));

        // The freelancer's private notes and budgets never reach the portal.
        $this->inTenant($sam, fn () => $abc->update(['notes' => 'PRIVATE-NOTE-TEXT']));
        $this->actingAs($alice)->get(route('portal.dashboard'))->assertDontSee('PRIVATE-NOTE-TEXT');
    }

    public function test_clients_see_only_their_own_sent_invoices(): void
    {
        $sam = $this->solo();
        $abc = $this->client($sam, 'ABC GmbH', 'abc@example.com');
        $xyz = $this->client($sam, 'XYZ Ltd', 'xyz@example.com');
        $alice = $this->portalUser($sam, $abc, 'Alice');
        $xavier = $this->portalUser($sam, $xyz, 'Xavier');

        $draft = $this->sentInvoice($sam, $abc);
        $this->actingAs($alice)->get(route('portal.invoice', $draft))->assertForbidden(); // drafts are private
        $this->actingAs($alice)->get(route('invoices.pdf', $draft))->assertForbidden();

        $this->actingAs($sam)->post(route('invoices.send', $draft))->assertRedirect();
        $this->assertSame('submitted', $draft->fresh()->status);

        $this->actingAs($alice)->get(route('portal.invoices'))->assertOk()->assertSee($draft->number)->assertSee('Amount due');
        $this->actingAs($alice)->get(route('portal.invoice', $draft))->assertOk()->assertSee('Download PDF');
        $pdf = $this->actingAs($alice)->get(route('invoices.pdf', $draft));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->actingAs($alice)->get(route('invoices.preview', $draft))->assertOk()->assertSee('Design');

        // The other client gets nothing of it.
        $this->actingAs($xavier)->get(route('portal.invoices'))->assertOk()->assertDontSee($draft->number);
        $this->actingAs($xavier)->get(route('portal.invoice', $draft))->assertForbidden();
        $this->actingAs($xavier)->get(route('invoices.pdf', $draft))->assertForbidden();
        $this->actingAs($xavier)->get(route('invoices.preview', $draft))->assertForbidden();

        // A client cannot change an invoice.
        $this->actingAs($alice)->post(route('invoices.send', $draft))->assertRedirect(route('portal.dashboard'));
        $this->actingAs($alice)->post(route('invoices.mark-paid', $draft), ['method' => 'cash', 'paid_on' => now()->toDateString()])->assertRedirect(route('portal.dashboard'));
        $this->assertSame('submitted', $draft->fresh()->status);

        // The freelancer can see that it was opened.
        $this->assertTrue(\App\Models\AuditLog::withoutGlobalScopes()->where('action', 'invoice.viewed')->where('auditable_id', $draft->id)->exists());
    }

    public function test_a_solo_freelancer_bills_a_client_without_a_company_approval_step(): void
    {
        $sam = $this->solo();
        $abc = $this->client($sam, 'ABC GmbH', 'abc@example.com');
        $invoice = $this->sentInvoice($sam, $abc, '5000');

        // Bill-to company makes no sense in a solo workspace.
        $this->actingAs($sam)->post(route('invoices.store'), [
            'bill_to_type' => 'company', 'issue_date' => now()->toDateString(), 'terms_days' => '14', 'currency' => 'INR',
            'invoice_type' => 'domestic', 'template' => 'professional', 'tax_treatment' => 'none',
        ])->assertSessionHasErrors('bill_to_type');

        $this->actingAs($sam)->post(route('invoices.send', $invoice))->assertRedirect();
        $this->actingAs($sam)->post(route('invoices.approve', $invoice))->assertForbidden();
        $this->actingAs($sam)->post(route('invoices.mark-paid', $invoice), ['method' => 'bank_transfer', 'paid_on' => now()->toDateString(), 'reference' => 'UTR1'])->assertRedirect();
        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(500000, $invoice->amount_paid_minor);

        $this->actingAs($sam)->get(route('invoices.index', ['status' => 'paid']))->assertSee($invoice->number);
        $this->actingAs($sam)->get(route('dashboard'))->assertOk()->assertSee('This month')->assertSee('5,000.00');
    }

    public function test_clients_are_not_listed_as_team_members_and_cannot_be_given_a_role(): void
    {
        $sam = $this->solo();
        $abc = $this->client($sam, 'ABC GmbH', 'abc@example.com');
        $alice = $this->portalUser($sam, $abc, 'Alice');
        $member = OrganizationMember::withoutGlobalScopes()->where('user_id', $alice->id)->firstOrFail();

        $this->actingAs($sam)->get(route('team.members'))->assertOk()->assertDontSee('Alice');
        $this->actingAs($sam)->get(route('team.member', $member))->assertNotFound();
        $this->actingAs($sam)->patch(route('team.update', $member), ['role' => 'owner'])->assertNotFound();
        $this->actingAs($sam)->get(route('team.roles'))->assertOk()->assertDontSee('>Client<', false);
    }

    public function test_client_directory_filters_and_search(): void
    {
        $sam = $this->solo();
        $this->client($sam, 'ABC GmbH', 'abc@example.com');
        $b = $this->client($sam, 'Bella Person', 'bella@example.com');
        $this->inTenant($sam, fn () => $b->update(['type' => 'individual', 'status' => 'inactive', 'country_code' => 'DE']));

        $this->actingAs($sam)->get(route('clients.index'))->assertSee('ABC GmbH')->assertSee('Bella Person');
        $this->actingAs($sam)->get(route('clients.index', ['filter' => 'individual']))->assertSee('Bella Person')->assertDontSee('ABC GmbH');
        $this->actingAs($sam)->get(route('clients.index', ['filter' => 'active']))->assertSee('ABC GmbH')->assertDontSee('Bella Person');
        $this->actingAs($sam)->get(route('clients.index', ['filter' => 'international']))->assertSee('Bella Person')->assertDontSee('ABC GmbH');
        $this->actingAs($sam)->get(route('clients.index', ['q' => 'bella']))->assertSee('Bella Person')->assertDontSee('ABC GmbH');
    }

    public function test_a_client_company_can_have_member_logins_with_fewer_rights(): void
    {
        $sam = $this->solo();
        $abc = $this->client($sam, 'ABC GmbH', 'abc@example.com');
        $xyz = $this->client($sam, 'XYZ Ltd', 'xyz@example.com');
        $project = $this->actingAs($sam)->post(route('projects.store'), ['name' => 'SEO', 'client_id' => $abc->id, 'status' => 'active', 'currency' => 'INR']);
        $project = \App\Models\Project::withoutGlobalScopes()->firstOrFail();
        $alice = $this->portalUser($sam, $abc, 'Alice');
        $this->actingAs($sam)->post(route('projects.deliverables.store', $project), ['title' => 'Report']);
        $invoice = $this->sentInvoiceFor($sam, $abc);

        $this->actingAs($alice)->get(route('portal.team'))->assertOk();
        $this->actingAs($alice)->post(route('portal.team.invite'), ['email' => 'bob@abc.example'])->assertRedirect();
        $inv = Invitation::withoutGlobalScopes()->where('role', 'client_member')->firstOrFail();
        $this->assertSame($abc->id, $inv->client_id);
        $bob = User::create(['name' => 'Bob', 'email' => 'bob@abc.example', 'password' => 'secret-pass-1']);
        $this->actingAs($bob)->post(route('invite.accept', $inv->token))->assertRedirect(route('portal.dashboard'));

        // A member sees the same company data...
        $this->actingAs($bob)->get(route('portal.project', $project->slug))->assertOk()->assertSee('Report');
        $this->actingAs($bob)->get(route('portal.invoice', $invoice))->assertOk();
        $this->actingAs($bob)->post(route('portal.messages.store'), ['body' => 'Hello from Bob'])->assertRedirect();
        $this->actingAs($sam)->get(route('messages.index', ['client' => $abc->id]))->assertSee('Hello from Bob');
        // ...but cannot decide, report payments, edit the company or invite people.
        $d = \App\Models\Deliverable::withoutGlobalScopes()->firstOrFail();
        $this->actingAs($bob)->post(route('portal.deliverables.approve', $d))->assertForbidden();
        $this->actingAs($bob)->post(route('portal.invoices.paid', $invoice), ['amount' => '10', 'method' => 'upi', 'paid_on' => now()->toDateString(), 'reference' => 'X'])->assertForbidden();
        $this->actingAs($bob)->post(route('portal.company.update'), ['name' => 'Hijacked'])->assertForbidden();
        $this->actingAs($bob)->post(route('portal.team.invite'), ['email' => 'eve@abc.example'])->assertForbidden();
        $this->actingAs($bob)->get(route('clients.index'))->assertRedirect(route('portal.dashboard'));
        $this->assertSame('ABC GmbH', Client::withoutGlobalScopes()->findOrFail($abc->id)->name);
        $this->actingAs($bob)->get(route('portal.dashboard'))->assertDontSee('Our team');

        // Another client's contact cannot touch this team; the main contact can remove a member.
        $xavier = $this->portalUser($sam, $xyz, 'Xavier');
        $member = \App\Models\OrganizationMember::withoutGlobalScopes()->where('user_id', $bob->id)->firstOrFail();
        $this->actingAs($xavier)->delete(route('portal.team.remove', $member))->assertNotFound();
        $this->actingAs($alice)->delete(route('portal.team.remove', $member))->assertRedirect();
        $this->actingAs($bob)->get(route('portal.dashboard'))->assertRedirect(route('onboarding.index'));

        // Owners cannot be removed; team roles are relabelled.
        $this->assertSame('Manager', \App\Enums\OrganizationRole::ProjectManager->label());
        $this->assertSame('Accountant', \App\Enums\OrganizationRole::Finance->label());
        $this->actingAs($sam)->get(route('team.roles'))->assertOk()->assertSee('Accountant')->assertDontSee('Client member');
    }

    private function sentInvoiceFor(User $owner, Client $client): Invoice
    {
        $before = Invoice::withoutGlobalScopes()->pluck('id')->all();
        $this->actingAs($owner)->post(route('invoices.store'), ['bill_to_type' => 'client', 'client_id' => $client->id, 'issue_date' => now()->toDateString(), 'terms_days' => '14', 'currency' => 'INR', 'invoice_type' => 'domestic', 'template' => 'professional', 'tax_treatment' => 'none']);
        $invoice = Invoice::withoutGlobalScopes()->whereNotIn('id', $before)->firstOrFail();
        $this->actingAs($owner)->post(route('invoices.items.add', $invoice), ['description' => 'Work', 'quantity' => 1, 'unit' => 'items', 'unit_rate' => '100']);
        $this->actingAs($owner)->post(route('invoices.send', $invoice));

        return $invoice->refresh();
    }
}
