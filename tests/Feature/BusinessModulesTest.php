<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\TimeOff;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

class BusinessModulesTest extends PortalTestCase
{
    private function quote($sam, $abc): Proposal
    {
        $this->actingAs($sam)->post(route('quotes.store'), [
            'client_id' => $abc->id, 'title' => 'Brand Refresh', 'currency' => 'INR', 'intro' => 'Logo and guidelines', 'tax_rate' => '18', 'tax_label' => 'GST',
            'items' => [['description' => 'Logo', 'quantity' => 1, 'unit_rate' => '30000'], ['description' => 'Guidelines', 'quantity' => 2, 'unit_rate' => '10000']],
        ])->assertRedirect();

        return Proposal::withoutGlobalScopes()->firstOrFail();
    }

    public function test_a_quote_is_sent_accepted_and_becomes_a_project(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $alice = $this->portalUser($sam, $abc, 'Alice');
        $q = $this->quote($sam, $abc);
        $this->assertSame(5000000, $q->subtotalMinor());
        $this->assertSame(5900000, $q->totalMinor(), '18% on top');

        $this->actingAs($alice)->get(route('portal.quotes.show', $q))->assertNotFound();   // still a draft
        $this->actingAs($sam)->post(route('quotes.send', $q))->assertRedirect();
        $this->actingAs($alice)->get(route('portal.quotes'))->assertOk()->assertSee('Brand Refresh');
        $this->actingAs($alice)->get(route('portal.quotes.show', $q))->assertOk()->assertSee('Logo')->assertSee('GST');

        $this->actingAs($alice)->post(route('portal.quotes.accept', $q), ['signed_name' => 'Alice Wonder'])->assertSessionHasErrors('agree');
        $this->actingAs($alice)->post(route('portal.quotes.accept', $q), ['signed_name' => 'Alice Wonder', 'agree' => 1])->assertRedirect();
        $q->refresh();
        $this->assertSame('accepted', $q->status);
        $project = Project::withoutGlobalScopes()->findOrFail($q->project_id);
        $this->assertSame('Brand Refresh', $project->name);
        $this->assertSame($abc->id, $project->client_id);
        $this->actingAs($alice)->post(route('portal.quotes.accept', $q), ['signed_name' => 'x', 'agree' => 1])->assertStatus(422);
        $this->actingAs($sam)->get(route('quotes.show', $q))->assertOk()->assertSee('Alice Wonder');
    }

    public function test_quote_rules_isolation_and_colleagues(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $xyz = $this->makeClient($sam, 'XYZ Ltd', 'xyz@example.com');
        $alice = $this->portalUser($sam, $abc, 'Alice');
        $xavier = $this->portalUser($sam, $xyz, 'Xavier');
        $q = $this->quote($sam, $abc);
        $this->actingAs($sam)->post(route('quotes.send', $q))->assertRedirect();
        $this->actingAs($xavier)->get(route('portal.quotes.show', $q))->assertNotFound();
        $this->actingAs($alice)->get(route('quotes.index'))->assertRedirect();   // staff pages are closed to clients

        $this->actingAs($sam)->post(route('quotes.store'), ['client_id' => $abc->id, 'title' => 'Bad', 'currency' => 'INR', 'items' => []])->assertSessionHasErrors('items');
        $this->actingAs($sam)->put(route('quotes.update', $q), ['client_id' => $abc->id, 'title' => 'x', 'currency' => 'INR', 'items' => [['description' => 'a', 'quantity' => 1, 'unit_rate' => 1]]])->assertStatus(422);
        $other = $this->solo('Other');
        $this->actingAs($other)->get(route('quotes.show', $q))->assertNotFound();

        $this->actingAs($alice)->post(route('portal.quotes.decline', $q), ['reason' => 'Too high'])->assertRedirect();
        $this->assertSame('declined', $q->refresh()->status);
        $this->actingAs($sam)->get(route('quotes.show', $q))->assertSee('Too high');
    }

    public function test_agreements_are_signed_with_a_record_and_locked_after_sending(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $alice = $this->portalUser($sam, $abc, 'Alice');
        $this->actingAs($sam)->post(route('agreements.store'), ['client_id' => $abc->id, 'title' => 'Services agreement', 'body' => "Scope: design.\nPayment: 14 days."])->assertRedirect();
        $a = Agreement::withoutGlobalScopes()->firstOrFail();
        $this->actingAs($sam)->get(route('agreements.create'))->assertOk()->assertSee('Non-disclosure agreement');
        $this->actingAs($alice)->get(route('portal.agreements.show', $a))->assertNotFound();

        $this->actingAs($sam)->post(route('agreements.send', $a))->assertRedirect();
        $a->refresh();
        $this->assertNotNull($a->body_hash);
        $this->actingAs($sam)->put(route('agreements.update', $a), ['client_id' => $abc->id, 'title' => 'x', 'body' => 'changed'])->assertStatus(422);

        $this->actingAs($alice)->get(route('portal.agreements.show', $a))->assertOk()->assertSee('Payment: 14 days.');
        $this->actingAs($alice)->post(route('portal.agreements.sign', $a), ['signed_name' => 'Alice Wonder'])->assertSessionHasErrors('agree');
        $this->actingAs($alice)->post(route('portal.agreements.sign', $a), ['signed_name' => 'Alice Wonder', 'agree' => 1])->assertRedirect();
        $a->refresh();
        $this->assertSame('signed', $a->status);
        $this->assertSame('Alice Wonder', $a->signed_name);
        $this->assertNotEmpty($a->signed_ip);
        $this->assertTrue($a->intact());

        $this->actingAs($alice)->post(route('portal.agreements.sign', $a), ['signed_name' => 'Again', 'agree' => 1])->assertStatus(422);
        $this->actingAs($sam)->post(route('agreements.void', $a))->assertStatus(422);
        $this->assertStringStartsWith('%PDF', $this->actingAs($alice)->get(route('portal.agreements.pdf', $a))->getContent());
        $this->assertStringStartsWith('%PDF', $this->actingAs($sam)->get(route('agreements.pdf', $a))->getContent());

        // Tampering after signing is detectable.
        Agreement::withoutGlobalScopes()->whereKey($a->id)->update(['body' => 'Different text']);
        $this->actingAs($sam)->get(route('agreements.show', $a))->assertSee('no longer matches');
    }

    public function test_expenses_with_receipts_and_billing_them_on_an_invoice(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $this->actingAs($sam)->post(route('expenses.store'), ['spent_on' => now()->toDateString(), 'category' => 'Software', 'description' => 'Font licence', 'amount' => '1500.50', 'currency' => 'INR', 'client_id' => $abc->id, 'is_billable' => 1, 'receipt' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf')])->assertRedirect();
        $this->actingAs($sam)->post(route('expenses.store'), ['spent_on' => now()->toDateString(), 'category' => 'Office', 'description' => 'Own stationery', 'amount' => '200', 'currency' => 'INR'])->assertRedirect();
        $this->actingAs($sam)->post(route('expenses.store'), ['spent_on' => now()->toDateString(), 'category' => 'Travel', 'description' => 'No client', 'amount' => '5', 'currency' => 'INR', 'is_billable' => 1])->assertSessionHasErrors('client_id');
        $this->actingAs($sam)->post(route('expenses.store'), ['spent_on' => now()->addDay()->toDateString(), 'category' => 'Office', 'description' => 'Future', 'amount' => '5', 'currency' => 'INR'])->assertSessionHasErrors('spent_on');
        $e = Expense::withoutGlobalScopes()->where('description', 'Font licence')->firstOrFail();
        $this->assertSame(150050, $e->amount_minor);
        $this->assertNotNull($e->receipt_file_id);
        $this->actingAs($sam)->get(route('expenses.index'))->assertOk()->assertSee('Font licence')->assertSee('Receipt')->assertSee('1,700.50');

        $invoice = $this->sentInvoice($sam, $abc, '1000', send: false);
        $this->actingAs($sam)->get(route('invoices.edit', [$invoice, 'step' => 2]))->assertSee('Billable expenses');
        $this->actingAs($sam)->post(route('invoices.import-expenses', $invoice))->assertRedirect();
        $invoice->refresh();
        $this->assertSame(100000 + 150050, $invoice->subtotal_minor);
        $this->assertNotNull($e->refresh()->billed_invoice_id);
        $this->actingAs($sam)->post(route('invoices.import-expenses', $invoice))->assertSessionHas('error');
        $this->actingAs($sam)->delete(route('expenses.destroy', $e))->assertStatus(422);

        // Removing the line frees the expense again.
        $item = \App\Models\InvoiceItem::withoutGlobalScopes()->where('invoice_id', $invoice->id)->where('source_type', Expense::class)->firstOrFail();
        $this->actingAs($sam)->delete(route('invoices.items.remove', [$invoice, $item]))->assertRedirect();
        $this->assertNull($e->refresh()->billed_invoice_id);
        $other = $this->solo('Other');
        $this->actingAs($other)->get(route('expenses.index'))->assertDontSee('Font licence');
    }

    public function test_public_booking_respects_hours_time_off_and_double_booking(): void
    {
        $sam = $this->solo();
        $days = [];
        foreach (range(1, 7) as $n) {
            $days[$n] = ['on' => 1, 'from' => '09:00', 'to' => '12:00'];
        }
        $this->actingAs($sam)->post(route('bookings.save'), ['slug' => 'sam-intro', 'title' => 'Intro call', 'duration_minutes' => 60, 'buffer_minutes' => 0, 'notice_hours' => 1, 'window_days' => 14, 'timezone' => 'UTC', 'days' => $days, 'active' => 1, 'location' => 'https://meet.example.com/sam'])->assertRedirect();
        $this->actingAs($sam)->post(route('bookings.save'), ['slug' => 'ab', 'title' => 'x', 'duration_minutes' => 60, 'buffer_minutes' => 0, 'notice_hours' => 1, 'window_days' => 14, 'timezone' => 'UTC'])->assertSessionHasErrors('slug');
        $page = BookingPage::firstOrFail();

        auth()->logout();
        $this->flushSession();
        $date = now('UTC')->addDays(2)->format('Y-m-d');
        $res = $this->get(route('book.show', ['sam-intro', 'date' => $date]))->assertOk()->assertSee('Intro call')->assertSee('09:00')->assertSee('11:00');
        $start = Carbon::parse($date.' 10:00', 'UTC');

        $this->post(route('book.store', 'sam-intro'), ['start' => $start->toIso8601String(), 'name' => 'Guest One', 'email' => 'guest@example.com'])->assertRedirect();
        $b = Booking::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('confirmed', $b->status);
        $this->get(route('book.confirmed', $b->token))->assertOk()->assertSee('You are booked');

        // The same slot cannot be booked twice, and an invented time is refused.
        $this->post(route('book.store', 'sam-intro'), ['start' => $start->toIso8601String(), 'name' => 'Guest Two', 'email' => 'two@example.com'])->assertSessionHasErrors('start');
        $this->post(route('book.store', 'sam-intro'), ['start' => $start->copy()->setTime(3, 0)->toIso8601String(), 'name' => 'Night', 'email' => 'n@example.com'])->assertSessionHasErrors('start');
        $this->assertSame(1, Booking::withoutGlobalScopes()->count());
        $this->get(route('book.show', ['sam-intro', 'date' => $date]))->assertDontSee('value="'.$start->toIso8601String().'"', false);

        // Guest can cancel with the private link, which frees the slot.
        $this->post(route('book.cancel', $b->token))->assertRedirect();
        $this->assertSame('cancelled', $b->refresh()->status);
        $this->post(route('book.store', 'sam-intro'), ['start' => $start->toIso8601String(), 'name' => 'Guest Two', 'email' => 'two@example.com'])->assertRedirect();

        // Time off blocks whole days.
        $this->actingAs($sam)->post(route('timeoff.store'), ['starts_on' => $date, 'ends_on' => $date, 'kind' => 'vacation'])->assertRedirect();
        $this->assertSame(1, TimeOff::withoutGlobalScopes()->count());
        $this->actingAs($sam)->post(route('timeoff.store'), ['starts_on' => $date, 'ends_on' => now()->subDay()->toDateString(), 'kind' => 'vacation'])->assertSessionHasErrors('ends_on');
        auth()->logout();
        $this->flushSession();
        $this->post(route('book.store', 'sam-intro'), ['start' => Carbon::parse($date.' 11:00', 'UTC')->toIso8601String(), 'name' => 'Late', 'email' => 'l@example.com'])->assertSessionHasErrors('start');

        BookingPage::whereKey($page->id)->update(['active' => false]);
        $this->get(route('book.show', 'sam-intro'))->assertNotFound();
        $this->get(route('book.show', 'nobody'))->assertNotFound();
    }
}
