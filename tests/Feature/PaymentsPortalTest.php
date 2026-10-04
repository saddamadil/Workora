<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Message;
use App\Models\Payment;
use App\Models\PaymentReport;

class PaymentsPortalTest extends PortalTestCase
{
    private function setup3(): array
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $xyz = $this->makeClient($sam, 'XYZ Ltd', 'xyz@example.com');

        return [$sam, $abc, $this->portalUser($sam, $abc, 'Alice'), $this->portalUser($sam, $xyz, 'Xavier')];
    }

    public function test_client_reports_a_payment_and_the_freelancer_confirms_it(): void
    {
        [$sam, $abc, $alice, $xavier] = $this->setup3();
        $invoice = $this->sentInvoice($sam, $abc, '1000');

        $this->actingAs($alice)->post(route('portal.invoices.paid', $invoice), ['amount' => '400', 'method' => 'upi', 'paid_on' => now()->toDateString()])->assertSessionHasErrors('reference');
        $this->actingAs($alice)->post(route('portal.invoices.paid', $invoice), ['amount' => '5000', 'method' => 'upi', 'paid_on' => now()->toDateString(), 'reference' => 'T1'])->assertSessionHasErrors('amount');
        $this->actingAs($xavier)->post(route('portal.invoices.paid', $invoice), ['amount' => '400', 'method' => 'upi', 'paid_on' => now()->toDateString(), 'reference' => 'T1'])->assertForbidden();
        $this->actingAs($alice)->post(route('portal.invoices.paid', $invoice), ['amount' => '400', 'method' => 'upi', 'paid_on' => now()->toDateString(), 'reference' => 'UTR-400'])->assertRedirect();

        // Nothing is recorded yet, but the freelancer is told.
        $this->assertSame(0, $invoice->fresh()->amount_paid_minor);
        $this->assertTrue(AppNotification::withoutGlobalScopes()->where('user_id', $sam->id)->where('type', 'payment')->exists());
        $this->actingAs($sam)->get(route('invoices.show', $invoice))->assertOk()->assertSee('Client says they paid')->assertSee('UTR-400');
        $this->actingAs($alice)->get(route('portal.invoice', $invoice))->assertSee('Waiting for your freelancer to confirm');

        $report = PaymentReport::withoutGlobalScopes()->firstOrFail();
        $this->actingAs($alice)->post(route('payment-reports.confirm', $report))->assertRedirect(route('portal.dashboard'));
        $this->actingAs($sam)->post(route('payment-reports.confirm', $report))->assertRedirect();
        $invoice->refresh();
        $this->assertSame(40000, $invoice->amount_paid_minor);
        $this->assertSame('partially_paid', $invoice->status);
        $this->assertSame('partial', $invoice->displayStatus());
        $this->assertSame('confirmed', $report->fresh()->status);
        $this->actingAs($sam)->post(route('payment-reports.confirm', $report))->assertStatus(422);

        // A report that cannot be matched is rejected and the client is told.
        $this->actingAs($alice)->post(route('portal.invoices.paid', $invoice), ['amount' => '100', 'method' => 'cash', 'paid_on' => now()->toDateString(), 'reference' => 'X'])->assertRedirect();
        $second = PaymentReport::withoutGlobalScopes()->where('reference', 'X')->firstOrFail();
        $this->actingAs($sam)->post(route('payment-reports.reject', $second))->assertRedirect();
        $this->assertSame(40000, $invoice->fresh()->amount_paid_minor);
        $this->assertTrue(AppNotification::withoutGlobalScopes()->where('user_id', $alice->id)->where('title', 'like', 'Payment not found%')->exists());
    }

    public function test_receipts_cancellation_and_refunds(): void
    {
        [$sam, $abc, $alice, $xavier] = $this->setup3();
        $paid = $this->sentInvoice($sam, $abc, '1000');
        $other = $this->sentInvoice($sam, $abc, '500');

        $this->actingAs($sam)->post(route('invoices.mark-paid', $paid), ['method' => 'bank_transfer', 'paid_on' => now()->toDateString(), 'reference' => 'R1'])->assertRedirect();
        $payment = Payment::withoutGlobalScopes()->firstOrFail();

        foreach ([$sam, $alice] as $who) {
            $r = $this->actingAs($who)->get(route('invoices.receipt', [$paid, $payment]));
            $r->assertOk();
            $this->assertStringStartsWith('%PDF', $r->getContent());
        }
        $this->actingAs($xavier)->get(route('invoices.receipt', [$paid, $payment]))->assertForbidden();
        $this->actingAs($sam)->get(route('invoices.receipt', [$other, $payment]))->assertNotFound();

        // Paid invoices cannot be cancelled, only refunded. Unpaid ones can be cancelled.
        $this->actingAs($sam)->post(route('invoices.cancel', $paid))->assertForbidden();
        $this->actingAs($sam)->post(route('invoices.cancel', $other))->assertRedirect();
        $this->assertSame('void', $other->fresh()->status);
        $this->actingAs($alice)->get(route('portal.invoices'))->assertSee('Cancelled');
        $this->actingAs($alice)->post(route('invoices.cancel', $paid))->assertRedirect(route('portal.dashboard'));

        $this->actingAs($sam)->post(route('invoices.refund', $other))->assertForbidden();
        $this->actingAs($sam)->post(route('invoices.refund', $paid))->assertRedirect();
        $this->assertSame('refunded', $paid->fresh()->status);
        $this->actingAs($alice)->get(route('portal.invoices'))->assertSee('Refunded');
        $stats = \App\Services\InvoiceStats::summarize(\App\Models\Invoice::withoutGlobalScopes()->get());
        $this->assertSame([], $stats['invoiced'], 'cancelled and refunded invoices are not counted as invoiced');
    }

    public function test_questions_pay_links_and_the_payments_dashboard(): void
    {
        [$sam, $abc, $alice] = $this->setup3();
        $this->actingAs($sam)->post(route('team.payment-profiles.store'), ['kind' => 'international', 'label' => 'PayPal', 'currency' => 'INR', 'account_holder' => 'Sam', 'bank_name' => 'PayPal', 'iban' => 'GB29NWBK60161331926819', 'payment_link' => 'https://paypal.me/sam'])->assertRedirect();
        $invoice = $this->sentInvoice($sam, $abc, '1000');
        $this->assertNotNull($invoice->payout_method_id);

        $this->actingAs($alice)->get(route('portal.invoice', $invoice))->assertOk()->assertSee('Pay online')->assertSee('https://paypal.me/sam', false)->assertSee('Ask a question');
        $this->actingAs($alice)->post(route('portal.messages.store'), ['invoice_id' => $invoice->id, 'body' => 'Is GST included?'])->assertRedirect();
        $this->assertSame($invoice->id, Message::withoutGlobalScopes()->firstOrFail()->invoice_id);
        $this->actingAs($sam)->get(route('messages.index', ['client' => $abc->id]))->assertSee('Is GST included?')->assertSee('About an invoice');

        $this->actingAs($sam)->get(route('payments.index'))->assertOk()->assertSee('Outstanding')->assertSee($invoice->number);
        $this->actingAs($alice)->get(route('portal.invoices'))->assertSee('Next due date');
    }
}
