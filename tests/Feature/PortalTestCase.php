<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FreelancerProfile;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Services\Workspaces;

/** Helpers for a freelancer who runs a solo workspace with clients that have portal logins. */
abstract class PortalTestCase extends WorkoraTestCase
{
    protected function solo(string $name = 'Sam'): User
    {
        $user = User::create(['name' => $name, 'email' => strtolower($name).'@example.com', 'password' => 'secret-pass-1']);
        FreelancerProfile::create(['user_id' => $user->id, 'country_code' => 'IN', 'address_line1' => '1 MG Road', 'city' => 'Bengaluru']);
        app(Workspaces::class)->createFor($user, $name.' Studio', 'solo');

        return $user->refresh();
    }

    protected function makeClient(User $owner, string $name, string $email): Client
    {
        $this->actingAs($owner)->post(route('clients.store'), ['name' => $name, 'email' => $email, 'type' => 'company', 'default_currency' => 'INR'])->assertRedirect();

        return $this->inTenant($owner, fn () => Client::where('name', $name)->firstOrFail());
    }

    protected function portalUser(User $owner, Client $client, string $name): User
    {
        $this->actingAs($owner)->post(route('clients.invite', $client))->assertRedirect();
        $invitation = Invitation::withoutGlobalScopes()->where('client_id', $client->id)->firstOrFail();
        $user = User::create(['name' => $name, 'email' => $invitation->email, 'password' => 'secret-pass-1']);
        $this->actingAs($user)->post(route('invite.accept', $invitation->token))->assertRedirect(route('portal.dashboard'));

        return $user->refresh();
    }

    protected function makeProject(Client $client, User $owner, string $name): Project
    {
        $this->actingAs($owner)->post(route('projects.store'), ['name' => $name, 'client_id' => $client->id, 'status' => 'active', 'currency' => 'INR'])->assertRedirect();

        return $this->inTenant($owner, fn () => Project::where('name', $name)->firstOrFail());
    }

    protected function sentInvoice(User $owner, Client $client, string $rate = '1000', bool $send = true, ?Project $project = null): Invoice
    {
        $before = Invoice::withoutGlobalScopes()->pluck('id')->all();
        $this->actingAs($owner)->post(route('invoices.store'), [
            'bill_to_type' => 'client', 'client_id' => $client->id, 'project_id' => $project?->id, 'issue_date' => now()->toDateString(), 'terms_days' => '14',
            'currency' => 'INR', 'invoice_type' => 'domestic', 'template' => 'professional', 'tax_treatment' => 'none',
        ])->assertRedirect();
        $invoice = Invoice::withoutGlobalScopes()->whereNotIn('id', $before)->firstOrFail();
        $this->actingAs($owner)->post(route('invoices.items.add', $invoice), ['description' => 'Design', 'quantity' => 1, 'unit' => 'items', 'unit_rate' => $rate])->assertRedirect();
        if ($send) {
            $this->actingAs($owner)->post(route('invoices.send', $invoice))->assertRedirect();
        }

        return $invoice->refresh();
    }
}
