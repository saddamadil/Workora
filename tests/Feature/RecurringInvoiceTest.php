<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\User;
use Illuminate\Support\Carbon;

class RecurringInvoiceTest extends PortalTestCase
{
    private function schedule(User $sam, string $freq = 'monthly', array $extra = []): RecurringInvoice
    {
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $invoice = $this->sentInvoice($sam, $abc, '1000', send: false);
        $this->actingAs($sam)->post(route('recurring.store', $invoice), ['frequency' => $freq, 'start_on' => now()->toDateString()] + $extra)->assertRedirect(route('recurring.index'));

        return RecurringInvoice::withoutGlobalScopes()->orderByDesc('id')->firstOrFail();
    }

    public function test_a_schedule_is_made_from_an_invoice_and_the_command_issues_it(): void
    {
        $sam = $this->solo();
        $r = $this->schedule($sam);
        $this->assertSame('active', $r->status);
        $this->assertSame(1, Invoice::withoutGlobalScopes()->count());

        $this->artisan('invoices:recurring')->assertSuccessful();
        $this->assertSame(2, Invoice::withoutGlobalScopes()->count());
        $new = Invoice::withoutGlobalScopes()->orderByDesc('id')->first();
        $this->assertSame('draft', $new->status);
        $this->assertSame(100000, $new->subtotal_minor);
        $r->refresh();
        $this->assertSame(1, $r->runs_count);
        $this->assertTrue($r->next_run_on->isFuture());

        // Running again the same day does nothing.
        $this->artisan('invoices:recurring')->assertSuccessful();
        $this->assertSame(2, Invoice::withoutGlobalScopes()->count());
        $this->actingAs($sam)->get(route('recurring.index'))->assertOk()->assertSee('ABC GmbH');
    }

    public function test_month_ends_do_not_drift_and_end_dates_stop_the_schedule(): void
    {
        $r = new RecurringInvoice(['frequency' => 'monthly']);
        $this->assertSame('2026-02-28', $r->advance(Carbon::parse('2026-01-31'))->toDateString());
        $this->assertSame('2026-08-31', (new RecurringInvoice(['frequency' => 'quarterly']))->advance(Carbon::parse('2026-05-31'))->toDateString());

        $sam = $this->solo();
        $r = $this->schedule($sam, 'weekly', ['ends_on' => now()->addDays(3)->toDateString()]);
        $this->artisan('invoices:recurring')->assertSuccessful();
        $this->assertSame('ended', $r->refresh()->status);
    }

    public function test_auto_send_notifies_the_client_and_paused_schedules_wait(): void
    {
        $sam = $this->solo();
        $r = $this->schedule($sam, 'monthly', ['auto_send' => 1]);
        $this->actingAs($sam)->post(route('recurring.toggle', $r))->assertRedirect();
        $this->artisan('invoices:recurring')->assertSuccessful();
        $this->assertSame(1, Invoice::withoutGlobalScopes()->count(), 'paused: nothing made');

        $this->actingAs($sam)->post(route('recurring.toggle', $r))->assertRedirect();
        $this->artisan('invoices:recurring')->assertSuccessful();
        $new = Invoice::withoutGlobalScopes()->orderByDesc('id')->first();
        $this->assertSame('submitted', $new->status);
    }

    public function test_run_now_delete_and_isolation(): void
    {
        $sam = $this->solo();
        $r = $this->schedule($sam);
        $other = $this->solo('Other');
        $this->actingAs($other)->post(route('recurring.run', $r))->assertNotFound();
        $this->actingAs($other)->get(route('recurring.index'))->assertOk()->assertDontSee('ABC GmbH');

        $this->actingAs($sam)->post(route('recurring.run', $r))->assertRedirect();
        $this->assertSame(2, Invoice::withoutGlobalScopes()->count());
        $this->actingAs($sam)->delete(route('recurring.destroy', $r))->assertRedirect();
        $this->assertSame(0, RecurringInvoice::withoutGlobalScopes()->count());
        $this->assertSame(2, Invoice::withoutGlobalScopes()->count(), 'invoices already made stay');
    }
}
