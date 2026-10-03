<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractMilestone;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TimeEntry;
use App\Models\Timesheet;

class ContractsInvoicesTest extends WorkoraTestCase
{
    private function approvedHours(string $hours = '5'): array
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->freelancer($owner, 'Fiona', 40000);
        $finance = $this->joinCompany($owner, 'Finn', 'finance');
        $project = $this->projectFor($owner, [$fiona]);
        $contract = $this->activeContract($owner, $fiona, ['hourly_rate_minor' => 60000]);

        $this->actingAs($fiona)->post(route('time.store'), ['project_id' => $project->id, 'entry_date' => now()->toDateString(), 'duration' => $hours])->assertRedirect();
        $this->actingAs($fiona)->post(route('time.submit-week'), ['week' => now()->toDateString()]);
        $ts = Timesheet::withoutGlobalScopes()->firstOrFail();
        $this->actingAs($owner)->post(route('timesheets.approve', $ts))->assertRedirect();

        return [$owner, $fiona, $finance, $contract, $project];
    }

    public function test_contract_lifecycle_draft_send_accept(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->freelancer($owner);
        $gus = $this->freelancer($owner, 'Gus');

        $this->actingAs($owner)->post(route('contracts.store'), [
            'user_id' => $fiona->id, 'title' => 'Design retainer', 'type' => 'hourly', 'currency' => 'INR', 'hourly_rate' => '750',
            'payment_cycle' => 'monthly', 'payment_terms_days' => 15, 'starts_on' => now()->toDateString(), 'terms' => 'Be nice.',
        ])->assertRedirect();
        $contract = Contract::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('draft', $contract->status);
        $this->assertSame(75000, $contract->hourly_rate_minor);
        $this->assertMatchesRegularExpression('/^CT-\d{4}-\d{3}$/', $contract->reference);

        // Drafts are invisible to the freelancer; nobody else sees it either.
        $this->actingAs($fiona)->get(route('contracts.show', $contract))->assertNotFound();
        $this->actingAs($fiona)->get(route('contracts.index'))->assertDontSee('Design retainer');

        $this->actingAs($owner)->post(route('contracts.send', $contract))->assertRedirect();
        $this->actingAs($fiona)->get(route('contracts.show', $contract))->assertOk()->assertSee('Accept contract')->assertSee('Be nice.');
        $this->actingAs($gus)->get(route('contracts.show', $contract))->assertNotFound();
        $this->actingAs($gus)->post(route('contracts.accept', $contract))->assertForbidden();
        $this->actingAs($owner)->post(route('contracts.accept', $contract))->assertForbidden();

        $this->actingAs($fiona)->post(route('contracts.accept', $contract))->assertRedirect();
        $fresh = $contract->fresh();
        $this->assertSame('active', $fresh->status);
        $this->assertNotNull($fresh->accepted_at);

        // Only drafts can be edited; ending needs a reason.
        $this->actingAs($owner)->get(route('contracts.edit', $contract))->assertStatus(422);
        $this->actingAs($owner)->post(route('contracts.terminate', $contract), [])->assertSessionHasErrors('reason');
        $this->actingAs($owner)->post(route('contracts.terminate', $contract), ['reason' => 'Project done'])->assertRedirect();
        $this->assertSame('terminated', $contract->fresh()->status);
    }

    public function test_only_money_roles_manage_contracts_and_pms_cannot_see_them(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->freelancer($owner);
        $pm = $this->joinCompany($owner, 'Pia', 'project_manager');
        $finance = $this->joinCompany($owner, 'Finn', 'finance');

        $this->actingAs($pm)->get(route('contracts.index'))->assertForbidden();
        $this->actingAs($pm)->get(route('contracts.create'))->assertForbidden();
        $this->actingAs($finance)->get(route('contracts.create'))->assertOk();
        $this->actingAs($fiona)->get(route('contracts.create'))->assertForbidden();
    }

    public function test_milestone_contract_flows_through_to_invoice(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->freelancer($owner);

        $this->actingAs($owner)->post(route('contracts.store'), [
            'user_id' => $fiona->id, 'title' => 'Site build', 'type' => 'milestone', 'currency' => 'INR',
            'payment_cycle' => 'on_completion', 'payment_terms_days' => 7, 'starts_on' => now()->toDateString(),
        ])->assertRedirect();
        $contract = Contract::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($owner)->post(route('contracts.send', $contract))->assertStatus(422); // no milestones yet
        $this->actingAs($owner)->post(route('contracts.milestones.add', $contract), ['title' => 'Design', 'amount' => '10000'])->assertRedirect();
        $this->actingAs($owner)->post(route('contracts.milestones.add', $contract), ['title' => 'Build', 'amount' => '20000.50'])->assertRedirect();
        $this->assertSame(3000050, (int) ContractMilestone::withoutGlobalScopes()->sum('amount_minor'));

        $this->actingAs($owner)->post(route('contracts.send', $contract))->assertRedirect();
        $this->actingAs($fiona)->post(route('contracts.accept', $contract))->assertRedirect();

        $design = ContractMilestone::withoutGlobalScopes()->where('title', 'Design')->firstOrFail();
        $this->actingAs($fiona)->post(route('contracts.milestones.approve', [$contract, $design]))->assertForbidden();
        $this->actingAs($fiona)->post(route('contracts.milestones.submit', [$contract, $design]))->assertRedirect();
        $this->actingAs($owner)->post(route('contracts.milestones.approve', [$contract, $design]))->assertRedirect();
        $this->assertSame('approved', $design->fresh()->status);

        // Invoice it.
        $this->actingAs($fiona)->post(route('invoices.store'), ['contract_id' => $contract->id])->assertRedirect();
        $invoice = Invoice::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(now()->addDays(7)->toDateString(), $invoice->due_date->toDateString());
        $this->actingAs($fiona)->post(route('invoices.import-milestones', $invoice))->assertRedirect();
        $this->assertSame('invoiced', $design->fresh()->status);
        $this->assertSame(1000000, $invoice->fresh()->total_minor);

        // Removing the line hands the milestone back.
        $item = $invoice->items()->withoutGlobalScopes()->firstOrFail();
        $this->actingAs($fiona)->delete(route('invoices.items.remove', [$invoice, $item]))->assertRedirect();
        $this->assertSame('approved', $design->fresh()->status);
        $this->assertSame(0, $invoice->fresh()->total_minor);
    }

    public function test_invoice_from_approved_time_then_approval_and_part_payments(): void
    {
        [$owner, $fiona, $finance, $contract] = $this->approvedHours('5');

        $this->actingAs($fiona)->post(route('invoices.store'), ['contract_id' => $contract->id])->assertRedirect();
        $invoice = Invoice::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('INV-'.now()->year.'-0001', $invoice->number);

        $this->actingAs($fiona)->post(route('invoices.import-time', $invoice))->assertRedirect();
        $invoice->refresh();
        $this->assertSame(300000, $invoice->total_minor, '5h x 600.00');
        $this->assertTrue(TimeEntry::withoutGlobalScopes()->firstOrFail()->isLocked());

        // The same hours cannot be billed twice.
        $this->actingAs($fiona)->post(route('invoices.import-time', $invoice))->assertSessionHas('error');
        $this->actingAs($fiona)->post(route('invoices.store'), ['contract_id' => $contract->id]);
        $second = Invoice::withoutGlobalScopes()->where('id', '!=', $invoice->id)->firstOrFail();
        $this->actingAs($fiona)->post(route('invoices.import-time', $second))->assertSessionHas('error');
        $this->assertSame('INV-'.now()->year.'-0002', $second->number);

        // Tax and a manual line.
        $this->actingAs($fiona)->put(route('invoices.update', $invoice), ['due_date' => now()->addDays(10)->toDateString(), 'tax_rate' => '18', 'notes' => 'Thanks'])->assertRedirect();
        $this->actingAs($fiona)->post(route('invoices.items.add', $invoice), ['description' => 'Stock photos', 'quantity' => 2, 'unit' => 'items', 'unit_rate' => '100'])->assertRedirect();
        $invoice->refresh();
        $this->assertSame(320000, $invoice->subtotal_minor);
        $this->assertSame(57600, $invoice->tax_minor);
        $this->assertSame(377600, $invoice->total_minor);

        // Only the issuer edits and submits; reviewers cannot touch a draft.
        $this->actingAs($owner)->post(route('invoices.items.add', $invoice), ['description' => 'x', 'quantity' => 1, 'unit' => 'items', 'unit_rate' => 1])->assertForbidden();
        $this->actingAs($fiona)->post(route('invoices.submit', $invoice))->assertRedirect();
        $this->assertSame('submitted', $invoice->fresh()->status);
        $this->actingAs($fiona)->post(route('invoices.items.add', $invoice), ['description' => 'x', 'quantity' => 1, 'unit' => 'items', 'unit_rate' => 1])->assertForbidden();

        // Approval: not the freelancer, not a project manager; finance or owner.
        $pm = $this->joinCompany($owner, 'Pia', 'project_manager');
        $this->actingAs($fiona)->post(route('invoices.approve', $invoice))->assertForbidden();
        $this->actingAs($pm)->post(route('invoices.approve', $invoice))->assertForbidden();
        $this->actingAs($finance)->post(route('invoices.reject', $invoice), [])->assertSessionHasErrors('reason');
        $this->actingAs($finance)->post(route('invoices.approve', $invoice))->assertRedirect();
        $invoice->refresh();
        $this->assertSame('approved', $invoice->status);
        $this->assertSame($finance->id, $invoice->approved_by);

        // Payments: part, too much, then the rest.
        $pay = fn ($amount, $who = null) => $this->actingAs($who ?? $finance)->post(route('invoices.payments.store', $invoice), ['amount' => $amount, 'method' => 'upi', 'paid_on' => now()->toDateString(), 'reference' => 'TXN1']);
        $pay('1000')->assertRedirect();
        $this->assertSame('partially_paid', $invoice->fresh()->status);
        $this->assertSame(100000, $invoice->fresh()->amount_paid_minor);
        $pay('99999')->assertSessionHasErrors('amount');
        $pay('100', $fiona)->assertForbidden();
        $pay('2776')->assertRedirect();
        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(377600, $invoice->amount_paid_minor);
        $this->assertSame(2, Payment::withoutGlobalScopes()->count());
        $pay('1')->assertForbidden();

        $this->actingAs($fiona)->get(route('payments.index'))->assertOk()->assertSee('3,776.00');
        $this->actingAs($finance)->get(route('payments.index'))->assertOk()->assertSee('Fiona');
        $this->actingAs($finance)->get(route('invoices.show', $invoice))->assertOk()->assertSee('Stock photos')->assertSee('Still owed');
    }

    public function test_rejected_invoice_can_be_fixed_and_resent_and_drafts_return_time(): void
    {
        [$owner, $fiona, $finance, $contract] = $this->approvedHours('2');

        $this->actingAs($fiona)->post(route('invoices.store'), ['contract_id' => $contract->id]);
        $invoice = Invoice::withoutGlobalScopes()->firstOrFail();
        $this->actingAs($fiona)->post(route('invoices.import-time', $invoice));
        $this->actingAs($fiona)->post(route('invoices.submit', $invoice));

        $this->actingAs($owner)->post(route('invoices.reject', $invoice), ['reason' => 'Wrong tax'])->assertRedirect();
        $this->assertSame('rejected', $invoice->fresh()->status);
        $this->actingAs($fiona)->get(route('invoices.show', $invoice))->assertSee('Wrong tax');
        $this->actingAs($fiona)->put(route('invoices.update', $invoice), ['due_date' => now()->addDays(5)->toDateString(), 'tax_rate' => '5'])->assertRedirect();
        $this->actingAs($fiona)->post(route('invoices.submit', $invoice))->assertRedirect();
        $this->assertSame('submitted', $invoice->fresh()->status);

        // A draft that is deleted frees its hours.
        $this->actingAs($fiona)->post(route('invoices.store'), ['contract_id' => $contract->id]);
        Invoice::withoutGlobalScopes()->whereKey($invoice->id)->update(['status' => 'draft']);
        $this->actingAs($fiona)->delete(route('invoices.destroy', $invoice))->assertRedirect(route('invoices.index'));
        $this->assertFalse(TimeEntry::withoutGlobalScopes()->firstOrFail()->isLocked());
    }

    public function test_freelancers_see_only_their_invoices_and_other_companies_see_none(): void
    {
        [$owner, $fiona, , $contract] = $this->approvedHours('1');
        $gus = $this->freelancer($owner, 'Gus');
        $bob = $this->userWithWorkspace('Bob');

        $this->actingAs($fiona)->post(route('invoices.store'), ['contract_id' => $contract->id]);
        $invoice = Invoice::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($gus)->get(route('invoices.show', $invoice))->assertForbidden();
        $this->actingAs($gus)->get(route('invoices.index'))->assertDontSee($invoice->number);
        $this->actingAs($bob)->get(route('invoices.show', $invoice))->assertNotFound();
        $this->actingAs($gus)->post(route('invoices.store'), ['contract_id' => $contract->id])->assertNotFound();
    }

    public function test_invoice_total_matches_the_approved_timesheet_for_odd_minutes(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->freelancer($owner);
        $project = $this->projectFor($owner, [$fiona]);
        $contract = $this->activeContract($owner, $fiona, ['hourly_rate_minor' => 80000]);

        foreach (['1:01', '0:07', '2:43'] as $d) {
            $this->actingAs($fiona)->post(route('time.store'), ['project_id' => $project->id, 'entry_date' => now()->toDateString(), 'duration' => $d])->assertRedirect();
        }
        $this->actingAs($fiona)->post(route('time.submit-week'), ['week' => now()->toDateString()]);
        $ts = Timesheet::withoutGlobalScopes()->firstOrFail();
        $this->actingAs($owner)->post(route('timesheets.approve', $ts));

        $this->actingAs($fiona)->post(route('invoices.store'), ['contract_id' => $contract->id]);
        $invoice = Invoice::withoutGlobalScopes()->firstOrFail();
        $this->actingAs($fiona)->post(route('invoices.import-time', $invoice));

        $this->assertSame($ts->fresh()->total_amount_minor, $invoice->fresh()->subtotal_minor);
    }
}
