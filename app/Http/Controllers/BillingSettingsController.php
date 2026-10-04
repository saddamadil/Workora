<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TaxProfile;
use App\Services\PlanLimits;
use App\Support\Countries;
use App\Support\Money;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Saved tax profiles, your own exchange rates, and the workspace's plan. */
class BillingSettingsController extends Controller
{
    public function __construct(private Tenancy $tenancy) {}

    private function guard(): void
    {
        abort_unless($this->tenancy->issuesOwnInvoices() || $this->tenancy->role()?->canApprovePayment() || $this->tenancy->role()?->seesMoney(), 403);
    }

    // ------------------------------------------------------------------ tax profiles

    public function taxProfiles(): View
    {
        $this->guard();

        return view('settings.tax-profiles', ['profiles' => TaxProfile::query()->orderByDesc('is_default')->orderBy('name')->get(), 'treatments' => Invoice::TREATMENTS, 'countries' => Countries::LIST]);
    }

    public function storeTaxProfile(Request $request): RedirectResponse
    {
        $this->guard();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'applies_to' => ['required', Rule::in(['all', 'domestic', 'international'])],
            'treatment' => ['required', Rule::in(array_keys(Invoice::TREATMENTS))],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'label' => ['nullable', 'string', 'max:30'],
            'place_of_supply' => ['nullable', 'string', 'max:80'],
            'sac_code' => ['nullable', 'regex:/^[0-9A-Za-z]{0,20}$/'],
            'lut_reference' => ['nullable', 'string', 'max:120'],
        ]);
        $default = $request->boolean('is_default');
        if ($default) {
            TaxProfile::query()->update(['is_default' => false]);
        }
        TaxProfile::create($data + ['rate' => (float) ($data['rate'] ?? 0), 'is_default' => $default]);

        return back()->with('status', 'Tax profile saved. You can apply it on any invoice.');
    }

    public function destroyTaxProfile(TaxProfile $profile): RedirectResponse
    {
        $this->guard();
        $profile->delete();

        return back()->with('status', 'Removed.');
    }

    // ------------------------------------------------------------------ exchange rates

    public function exchangeRates(): View
    {
        $this->guard();

        return view('settings.exchange-rates', ['rates' => ExchangeRate::query()->orderByDesc('effective_on')->orderBy('from_currency')->limit(100)->get(), 'currencies' => array_keys(Money::CURRENCIES)]);
    }

    public function storeExchangeRate(Request $request): RedirectResponse
    {
        $this->guard();
        $data = $request->validate([
            'from_currency' => ['required', Rule::in(array_keys(Money::CURRENCIES))],
            'to_currency' => ['required', Rule::in(array_keys(Money::CURRENCIES)), 'different:from_currency'],
            'rate' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'effective_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:120'],
        ]);
        ExchangeRate::create($data);

        return back()->with('status', 'Rate saved. It is suggested on international invoices; you can still change it there.');
    }

    public function destroyExchangeRate(ExchangeRate $rate): RedirectResponse
    {
        $this->guard();
        $rate->delete();

        return back()->with('status', 'Removed.');
    }

    // ------------------------------------------------------------------ plan

    public function plan(PlanLimits $limits): View
    {
        return view('settings.plan', ['plan' => $limits->plan(), 'usage' => $limits->usage(), 'limits' => $limits, 'labels' => PlanLimits::LABELS]);
    }

    /** Platform admin only: move any workspace to another plan. There is no billing; the plan is a limit. */
    public function adminPlans(Request $request): View
    {
        $this->adminOnly($request);
        $subs = Subscription::withoutGlobalScopes()->orderByDesc('id')->get()->unique('organization_id')->keyBy('organization_id');

        return view('admin.plans', ['orgs' => Organization::query()->orderBy('name')->get(), 'subs' => $subs, 'plans' => Plan::query()->orderBy('position')->get()]);
    }

    public function setPlan(Request $request, Organization $organization): RedirectResponse
    {
        $this->adminOnly($request);
        $data = $request->validate(['plan_id' => ['required', 'uuid', Rule::exists('plans', 'id')]]);
        Subscription::withoutGlobalScopes()->create(['organization_id' => $organization->id, 'plan_id' => $data['plan_id'], 'status' => 'active', 'starts_at' => now()]);

        return back()->with('status', 'Plan changed for '.$organization->name.'.');
    }

    private function adminOnly(Request $request): void
    {
        $email = config('workora.platform_admin_email');
        abort_unless($email && strcasecmp($email, $request->user()->email) === 0, 404);
    }
}
