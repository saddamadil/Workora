<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TaxProfile;
use App\Models\User;

class BillingModelsTest extends PortalTestCase
{
    public function test_line_discounts_flow_into_totals_and_the_pdf(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $invoice = $this->sentInvoice($sam, $abc, '1000', send: false);
        $this->actingAs($sam)->post(route('invoices.items.add', $invoice), ['description' => 'Audit', 'quantity' => 2, 'unit' => 'items', 'unit_rate' => '500', 'discount_percent' => '10'])->assertRedirect();
        $this->actingAs($sam)->post(route('invoices.items.add', $invoice), ['description' => 'Bad', 'quantity' => 1, 'unit' => 'items', 'unit_rate' => '5', 'discount_percent' => '150'])->assertSessionHasErrors('discount_percent');

        $invoice->refresh();
        $this->assertSame(100000 + 90000, $invoice->subtotal_minor, '2 x 500 less 10% is 900');
        $this->actingAs($sam)->get(route('invoices.edit', [$invoice, 'step' => 2]))->assertOk()->assertSee('10%');
        $html = $this->actingAs($sam)->get(route('invoices.preview', $invoice));
        $html->assertSee('Discount')->assertSee('Disc.')->assertSee('2,000.00')->assertSee('-₹100.00', false);
        $pdf = $this->actingAs($sam)->get(route('invoices.pdf', $invoice));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // Without any discount the column stays out of the way.
        $plain = $this->sentInvoice($sam, $abc, '300', send: false);
        $this->actingAs($sam)->get(route('invoices.preview', $plain))->assertDontSee('Disc.');
    }

    public function test_tax_profiles_and_exchange_rates_are_suggested_not_forced(): void
    {
        $sam = $this->solo();
        $this->actingAs($sam)->post(route('settings.tax-profiles.store'), ['name' => 'GST 18% India', 'treatment' => 'gst_intra', 'rate' => '18', 'applies_to' => 'domestic', 'place_of_supply' => 'Karnataka', 'sac_code' => '998314', 'is_default' => 1])->assertRedirect();
        $this->actingAs($sam)->post(route('settings.tax-profiles.store'), ['name' => 'Export LUT', 'treatment' => 'export_lut', 'applies_to' => 'international', 'lut_reference' => 'AD123', 'is_default' => 1])->assertRedirect();
        $this->assertSame(1, TaxProfile::withoutGlobalScopes()->where('is_default', true)->count(), 'only one default');
        $this->actingAs($sam)->post(route('settings.tax-profiles.store'), ['name' => 'x', 'treatment' => 'made-up', 'applies_to' => 'all'])->assertSessionHasErrors('treatment');

        $this->actingAs($sam)->post(route('settings.exchange-rates.store'), ['from_currency' => 'USD', 'to_currency' => 'INR', 'rate' => '83', 'effective_on' => now()->subMonth()->toDateString()])->assertRedirect();
        $this->actingAs($sam)->post(route('settings.exchange-rates.store'), ['from_currency' => 'USD', 'to_currency' => 'INR', 'rate' => '84.25', 'effective_on' => now()->toDateString()])->assertRedirect();
        $this->actingAs($sam)->post(route('settings.exchange-rates.store'), ['from_currency' => 'USD', 'to_currency' => 'USD', 'rate' => '1', 'effective_on' => now()->toDateString()])->assertSessionHasErrors('to_currency');
        $this->actingAs($sam)->post(route('settings.exchange-rates.store'), ['from_currency' => 'USD', 'to_currency' => 'INR', 'rate' => '0', 'effective_on' => now()->toDateString()])->assertSessionHasErrors('rate');

        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $this->actingAs($sam)->get(route('invoices.create', ['client' => $abc->id]))->assertOk()->assertSee('Apply a saved tax profile')->assertSee('GST 18% India')->assertSee('84.25');
        $this->actingAs($sam)->get(route('settings.tax-profiles'))->assertOk()->assertSee('Export LUT');
        $this->actingAs($sam)->get(route('settings.exchange-rates'))->assertOk()->assertSee('84.25');

        // Only the person's own workspace data shows.
        $other = $this->solo('Other');
        $this->actingAs($other)->get(route('settings.tax-profiles'))->assertDontSee('Export LUT');
        $this->actingAs($other)->delete(route('settings.tax-profiles.destroy', TaxProfile::withoutGlobalScopes()->firstOrFail()))->assertNotFound();
        $this->assertSame(2, ExchangeRate::withoutGlobalScopes()->count());
    }

    public function test_plan_limits_stop_adding_more_and_the_admin_can_change_plans(): void
    {
        $sam = $this->solo();
        $free = Plan::where('slug', 'free')->firstOrFail();
        $this->assertSame($free->id, Subscription::withoutGlobalScopes()->where('organization_id', $this->orgOf($sam)->id)->firstOrFail()->plan_id, 'new workspaces start on Free');
        $this->actingAs($sam)->get(route('settings.plan'))->assertOk()->assertSee('Free')->assertSee('of 5');

        foreach (range(1, 5) as $i) {
            $this->makeClient($sam, "Client $i", "c$i@example.com");
        }
        $this->actingAs($sam)->post(route('clients.store'), ['name' => 'Sixth', 'email' => 's@example.com'])->assertSessionHasErrors('plan');
        $this->assertSame(5, \App\Models\Client::withoutGlobalScopes()->count());
        $this->actingAs($sam)->get(route('clients.create'))->assertOk();

        // Only the platform admin may change a plan, and no one else can even see the page.
        config(['workora.platform_admin_email' => 'boss@example.com']);
        $boss = User::create(['name' => 'Boss', 'email' => 'boss@example.com', 'password' => 'secret-pass-1']);
        $this->actingAs($sam)->get(route('admin.plans'))->assertNotFound();
        $pro = Plan::where('slug', 'pro')->firstOrFail();
        $this->actingAs($sam)->post(route('admin.plans.set', $this->orgOf($sam)), ['plan_id' => $pro->id])->assertNotFound();
        $this->workspaceFor($boss);
        $this->actingAs($boss)->get(route('admin.plans'))->assertOk()->assertSee('Sam Studio');
        $this->actingAs($boss)->post(route('admin.plans.set', $this->orgOf($sam)), ['plan_id' => $pro->id])->assertRedirect();

        $this->actingAs($sam)->post(route('clients.store'), ['name' => 'Sixth', 'email' => 's@example.com'])->assertSessionHasNoErrors();
        $this->assertSame(6, \App\Models\Client::withoutGlobalScopes()->count());
    }

    private function workspaceFor(User $user): void
    {
        app(\App\Services\Workspaces::class)->createFor($user, 'Boss HQ', 'solo');
    }
}
