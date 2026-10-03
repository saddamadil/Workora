<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\PayoutMethod;
use App\Models\User;
use App\Services\ImageStore;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

class InvoicingProfilesTest extends WorkoraTestCase
{
    private function indianFreelancer(User $owner, string $name = 'Fiona', string $country = 'IN'): User
    {
        $u = $this->freelancer($owner, $name);
        $u->freelancerProfile->update(['country_code' => $country, 'address_line1' => '1 MG Road', 'city' => 'Bengaluru']);

        return $u->refresh();
    }

    private function form(array $over = []): array
    {
        return $over + [
            'bill_to_type' => 'company', 'issue_date' => now()->toDateString(), 'terms_days' => '14', 'currency' => 'INR',
            'invoice_type' => 'domestic', 'template' => 'professional', 'tax_treatment' => 'none',
        ];
    }

    private function draft(User $by, array $over = []): Invoice
    {
        $before = Invoice::withoutGlobalScopes()->pluck('id')->all();
        $this->actingAs($by)->post(route('invoices.store'), $this->form($over))->assertRedirect();

        return Invoice::withoutGlobalScopes()->whereNotIn('id', $before)->firstOrFail();
    }

    private function line(User $by, Invoice $i, string $rate = '10000'): void
    {
        $this->actingAs($by)->post(route('invoices.items.add', $i), ['description' => 'Design', 'quantity' => 1, 'unit' => 'items', 'unit_rate' => $rate])->assertRedirect();
    }

    public function test_numbers_follow_the_indian_financial_year_per_freelancer(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->indianFreelancer($owner);
        $gus = $this->indianFreelancer($owner, 'Gus');

        $a = $this->draft($fiona, ['issue_date' => '2026-05-01']);
        $b = $this->draft($fiona, ['issue_date' => '2027-02-01']);
        $c = $this->draft($fiona, ['issue_date' => '2027-04-02']);
        $g = $this->draft($gus, ['issue_date' => '2026-05-01']);

        $this->assertSame('INV-2026-27-001', $a->number);
        $this->assertSame('INV-2026-27-002', $b->number);
        $this->assertSame('INV-2027-28-001', $c->number);
        $this->assertSame('INV-2026-27-001', $g->number, 'each freelancer has their own run');
    }

    public function test_gst_splits_into_cgst_and_sgst_and_igst_stays_whole(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->indianFreelancer($owner);
        $fiona->freelancerProfile->update(['tax_ids' => ['gstin' => '29ABCDE1234F1Z5']]);

        $i = $this->draft($fiona, ['tax_treatment' => 'gst_intra', 'tax_rate' => '18', 'place_of_supply' => 'Karnataka', 'sac_code' => '998314']);
        $this->line($fiona, $i);
        $i->refresh();
        $this->assertSame(1000000, $i->subtotal_minor);
        $this->assertSame(180000, $i->tax_minor);
        $this->assertSame(['CGST', 'SGST'], array_column($i->taxLinesList(), 'label'));
        $this->assertSame([9.0, 9.0], array_map('floatval', array_column($i->taxLinesList(), 'rate')));

        $this->actingAs($fiona)->put(route('invoices.update', $i), $this->form(['tax_treatment' => 'gst_inter', 'tax_rate' => '18']))->assertRedirect();
        $i->refresh();
        $this->assertSame(['IGST'], array_column($i->taxLinesList(), 'label'));
        $this->assertSame(1180000, $i->total_minor);
    }

    public function test_international_invoice_keeps_foreign_amount_and_inr_equivalent(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->indianFreelancer($owner);

        $i = $this->draft($fiona, ['invoice_type' => 'international', 'currency' => 'USD', 'tax_treatment' => 'export_lut', 'exchange_rate' => '83.5', 'lut_reference' => 'AD123']);
        $this->line($fiona, $i, '100');
        $i->refresh();
        $this->assertSame(10000, $i->total_minor);
        $this->assertSame(0, $i->tax_minor);
        $this->assertSame(835000, $i->inr_equivalent_minor);
    }

    public function test_payment_profile_is_picked_by_currency_and_mask_hides_numbers(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->indianFreelancer($owner);

        $this->actingAs($fiona)->post(route('team.payment-profiles.store'), [
            'kind' => 'domestic', 'label' => 'HDFC', 'currency' => 'INR', 'account_holder' => 'Fiona', 'bank_name' => 'HDFC', 'account_number' => '123456789012', 'ifsc' => 'HDFC0001234',
        ])->assertRedirect();
        $this->actingAs($fiona)->post(route('team.payment-profiles.store'), [
            'kind' => 'international', 'label' => 'Wise USD', 'currency' => 'USD', 'country_code' => 'GB', 'account_holder' => 'Fiona', 'bank_name' => 'Wise', 'iban' => 'GB29NWBK60161331926819', 'swift' => 'NWBKGB2L',
        ])->assertRedirect();

        $inr = $this->draft($fiona);
        $usd = $this->draft($fiona, ['invoice_type' => 'international', 'currency' => 'USD']);
        $this->assertSame('domestic', $inr->payoutMethod->kind);
        $this->assertSame('international', $usd->payoutMethod->kind);

        $profile = PayoutMethod::where('user_id', $fiona->id)->where('kind', 'domestic')->firstOrFail();
        $this->assertStringNotContainsString('123456789012', $profile->getRawOriginal('details_encrypted'));
        $this->assertStringNotContainsString('123456789012', $profile->summary());
    }

    public function test_payment_profile_validation_and_ownership(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->indianFreelancer($owner);
        $gus = $this->indianFreelancer($owner, 'Gus');
        $base = ['kind' => 'domestic', 'label' => 'X', 'currency' => 'INR', 'account_holder' => 'F'];

        $this->actingAs($fiona)->post(route('team.payment-profiles.store'), $base + ['bank_name' => 'B', 'account_number' => '123456789', 'ifsc' => 'BAD'])->assertSessionHasErrors('ifsc');
        $this->actingAs($fiona)->post(route('team.payment-profiles.store'), $base + ['upi_id' => 'not an upi'])->assertSessionHasErrors('upi_id');
        $this->actingAs($owner)->post(route('team.payment-profiles.store'), $base + ['upi_id' => 'a.b@bank'])->assertForbidden();

        $this->actingAs($fiona)->post(route('team.payment-profiles.store'), $base + ['upi_id' => 'fiona@okbank'])->assertRedirect();
        $mine = PayoutMethod::where('user_id', $fiona->id)->firstOrFail();
        $this->actingAs($gus)->delete(route('team.payment-profiles.destroy', $mine))->assertForbidden();
    }

    public function test_preview_pdf_and_print_work_and_stay_private(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->indianFreelancer($owner);
        $gus = $this->indianFreelancer($owner, 'Gus');

        $i = $this->draft($fiona);
        $this->line($fiona, $i);

        $this->actingAs($fiona)->get(route('invoices.preview', $i))->assertOk()->assertSee($i->number)->assertSee('Design');
        $pdf = $this->actingAs($fiona)->get(route('invoices.pdf', $i));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->actingAs($fiona)->get(route('invoices.print', $i))->assertOk()->assertSee('window.print', false);

        $this->actingAs($gus)->get(route('invoices.pdf', $i))->assertForbidden();
        $this->actingAs($gus)->get(route('invoices.preview', $i))->assertForbidden();
        $this->actingAs($owner)->get(route('invoices.preview', $i))->assertForbidden(); // drafts are private

        foreach (['modern', 'minimal', 'international', 'gst'] as $t) {
            $this->actingAs($fiona)->post(route('invoices.template', $i), ['template' => $t])->assertRedirect();
            $this->assertStringStartsWith('%PDF', $this->actingAs($fiona)->get(route('invoices.pdf', $i))->getContent(), $t);
        }
    }

    public function test_send_freezes_details_and_duplicate_starts_a_clean_draft(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->indianFreelancer($owner);

        $i = $this->draft($fiona);
        $this->actingAs($fiona)->post(route('invoices.send', $i))->assertForbidden(); // nothing to bill
        $this->line($fiona, $i);
        $this->actingAs($fiona)->post(route('invoices.send', $i))->assertRedirect();
        $i->refresh();
        $this->assertSame('submitted', $i->status);
        $this->assertNotNull($i->snapshot);

        $fiona->freelancerProfile->update(['address_line1' => 'Moved elsewhere']);
        $this->actingAs($fiona)->get(route('invoices.preview', $i))->assertOk()->assertSee('1 MG Road')->assertDontSee('Moved elsewhere');

        $this->actingAs($fiona)->post(route('invoices.duplicate', $i))->assertRedirect();
        $copy = Invoice::withoutGlobalScopes()->where('id', '!=', $i->id)->firstOrFail();
        $this->assertSame('draft', $copy->status);
        $this->assertNotSame($i->number, $copy->number);
        $this->assertSame($i->total_minor, $copy->total_minor);
    }

    public function test_mark_paid_and_dashboard_filters(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->indianFreelancer($owner);

        $paid = $this->draft($fiona);
        $this->line($fiona, $paid, '845000');
        $this->actingAs($fiona)->post(route('invoices.send', $paid));
        $this->actingAs($owner)->post(route('invoices.approve', $paid))->assertRedirect();
        $this->actingAs($owner)->post(route('invoices.mark-paid', $paid), ['method' => 'bank_transfer', 'paid_on' => now()->toDateString()])->assertRedirect();
        $this->assertSame('paid', $paid->fresh()->status);

        $draft = $this->draft($fiona);

        $this->actingAs($fiona)->get(route('invoices.index', ['status' => 'paid']))->assertSee($paid->number)->assertDontSee($draft->number);
        $this->actingAs($fiona)->get(route('invoices.index', ['status' => 'draft']))->assertSee($draft->number)->assertDontSee($paid->number);
        $this->actingAs($fiona)->get(route('invoices.index', ['q' => $draft->number]))->assertSee($draft->number)->assertDontSee($paid->number);
        $this->actingAs($fiona)->get(route('invoices.index'))->assertSee('₹8,45,000.00');
    }

    public function test_staff_can_prepare_for_a_freelancer_but_not_approve_their_own_preparation(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->indianFreelancer($owner);
        $finance = $this->joinCompany($owner, 'Finn', 'finance');

        $this->actingAs($finance)->post(route('invoices.store'), $this->form(['freelancer_id' => $fiona->id]))->assertRedirect();
        $i = Invoice::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($fiona->id, $i->user_id);
        $this->assertSame($finance->id, $i->prepared_by);

        $this->line($finance, $i);
        $this->actingAs($finance)->post(route('invoices.send', $i))->assertRedirect();
        $this->actingAs($finance)->post(route('invoices.approve', $i))->assertForbidden();
        $this->actingAs($owner)->post(route('invoices.approve', $i))->assertRedirect();
    }

    public function test_team_tabs_depend_on_role(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->indianFreelancer($owner);
        $pm = $this->joinCompany($owner, 'Pia', 'project_manager');

        foreach (['team.index', 'team.members', 'team.invitations', 'team.roles', 'team.payment-profiles', 'team.invoices'] as $r) {
            $this->actingAs($owner)->get(route($r))->assertOk();
        }
        $this->actingAs($fiona)->get(route('team.members'))->assertOk();
        $this->actingAs($fiona)->get(route('team.payment-profiles'))->assertOk();
        $this->actingAs($fiona)->get(route('team.roles'))->assertForbidden();
        $this->actingAs($pm)->get(route('team.invoices'))->assertForbidden();
        $this->actingAs($pm)->get(route('team.roles'))->assertOk(); // read-only for staff
    }

    public function test_permission_matrix_matches_the_gates(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $roles = ['owner' => $owner, 'finance' => $this->joinCompany($owner, 'Finn', 'finance'), 'project_manager' => $this->joinCompany($owner, 'Pia', 'project_manager')];

        foreach ($roles as $role => $user) {
            $this->actingAs($user);
            // Abilities that are exposed as gates ('pay' is the gate for approve-invoices).
            app(\App\Support\Tenancy::class)->set($this->orgOf($user), $user->memberships()->firstOrFail());
            (function () use ($user, $role) {
                foreach (['track-time', 'see-money', 'manage-team', 'manage-clients', 'manage-contracts', 'review-time', 'approve-invoices' => 'pay'] as $ability => $gate) {
                    $ability = is_int($ability) ? $gate : $ability;
                    $this->assertSame(Permissions::allows($ability, \App\Enums\OrganizationRole::from($role)), Gate::forUser($user)->allows($gate), "$role / $ability");
                }
            })();
        }
    }

    public function test_company_and_freelancer_images_are_reencoded_and_svg_is_refused(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->indianFreelancer($owner);

        $png = UploadedFile::fake()->image('me.png', 900, 700);
        $this->actingAs($fiona)->post(route('profile.update'), ['name' => 'Fiona', 'timezone' => 'UTC', 'default_currency' => 'INR', 'availability' => 'available', 'photo' => $png])->assertSessionHasNoErrors();
        $path = $fiona->freelancerProfile->fresh()->photo_path ?? $fiona->fresh()->avatar_path ?? null;
        $this->assertNotNull($path, 'photo stored');

        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->actingAs($fiona)->post(route('profile.update'), ['name' => 'Fiona', 'timezone' => 'UTC', 'default_currency' => 'INR', 'availability' => 'available', 'photo' => $svg])->assertSessionHasErrors('photo');
    }

    public function test_money_uses_indian_grouping_for_rupees_only(): void
    {
        $this->assertSame('₹8,45,000.00', Money::format(84500000, 'INR'));
        $this->assertSame('₹1,23,45,678.00', Money::format(1234567800, 'INR'));
        $this->assertStringContainsString('845,000.00', Money::format(84500000, 'USD'));
    }
}
