<?php

namespace App\Http\Controllers;

use App\Models\OrganizationMember;
use App\Models\PayoutMethod;
use App\Support\Countries;
use App\Support\Money;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Payment profiles: where a freelancer is paid, kept once so no invoice needs the details retyped.
 * A freelancer manages their own. People who pay them see a masked summary here and the full
 * details on the invoice itself.
 */
class PaymentProfileController extends Controller
{
    public function __construct(private Tenancy $tenancy) {}

    public function index(Request $request): View
    {
        $isFreelancer = $this->tenancy->issuesOwnInvoices();
        abort_unless($isFreelancer || Gate::allows('pay') || Gate::allows('manage-team'), 403);

        $mine = $isFreelancer ? PayoutMethod::where('user_id', $request->user()->id)->orderByDesc('is_default')->orderBy('kind')->get() : collect();

        $freelancers = $isFreelancer ? collect() : OrganizationMember::query()->with('user:id,name,email,avatar_path', 'user.freelancerProfile')
            ->where('member_type', 'freelancer')->where('status', 'active')->get()
            ->each(fn ($m) => $m->setRelation('profiles', PayoutMethod::where('user_id', $m->user_id)->orderByDesc('is_default')->get()));

        return view('team.payment-profiles', [
            'isFreelancer' => $isFreelancer,
            'mine' => $mine,
            'freelancers' => $freelancers,
            'currencies' => array_keys(Money::CURRENCIES),
            'countries' => Countries::LIST,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->tenancy->issuesOwnInvoices(), 403);

        [$attrs, $details] = $this->validated($request);
        $profile = PayoutMethod::create($attrs + ['user_id' => $request->user()->id, 'details_encrypted' => $details]);

        // The first profile of each kind becomes that kind's default.
        if (! PayoutMethod::where('user_id', $request->user()->id)->where('kind', $profile->kind)->where('is_default', true)->exists()) {
            $profile->update(['is_default' => true]);
        }

        return back()->with('status', 'Payment profile saved.');
    }

    public function update(Request $request, PayoutMethod $profile): RedirectResponse
    {
        $this->ownOrFail($request, $profile);

        [$attrs, $details] = $this->validated($request);
        $profile->update($attrs + ['details_encrypted' => $details]);

        return back()->with('status', 'Payment profile updated.');
    }

    public function makeDefault(Request $request, PayoutMethod $profile): RedirectResponse
    {
        $this->ownOrFail($request, $profile);

        PayoutMethod::where('user_id', $profile->user_id)->where('kind', $profile->kind)->update(['is_default' => false]);
        $profile->update(['is_default' => true]);

        return back()->with('status', 'Default updated.');
    }

    public function destroy(Request $request, PayoutMethod $profile): RedirectResponse
    {
        $this->ownOrFail($request, $profile);
        $profile->delete();

        return back()->with('status', 'Payment profile removed.');
    }

    private function ownOrFail(Request $request, PayoutMethod $profile): void
    {
        abort_unless($this->tenancy->issuesOwnInvoices() && $profile->user_id === $request->user()->id, 403);
    }

    /** @return array{0: array, 1: array<string, string>} model attributes, encrypted details */
    private function validated(Request $request): array
    {
        $kind = $request->input('kind') === PayoutMethod::INTERNATIONAL ? PayoutMethod::INTERNATIONAL : PayoutMethod::DOMESTIC;

        // Spaces and case do not matter in these; normalise before checking the format.
        $request->merge([
            'iban' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('iban'))),
            'swift' => strtoupper(trim((string) $request->input('swift'))),
            'ifsc' => strtoupper(trim((string) $request->input('ifsc'))),
            'account_number' => preg_replace('/[\s-]+/', '', (string) $request->input('account_number')),
            'upi_id' => strtolower(trim((string) $request->input('upi_id'))),
        ]);

        $common = [
            'label' => ['required', 'string', 'max:120'],
            'currency' => ['required', Rule::in(array_keys(Money::CURRENCIES))],
            'country_code' => ['nullable', 'string', 'size:2'],
            'account_holder' => ['required', 'string', 'max:160'],
            'bank_name' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];

        if ($kind === PayoutMethod::DOMESTIC) {
            $data = $request->validate($common + [
                'account_number' => ['nullable', 'regex:/^\d{6,20}$/'],
                'ifsc' => ['nullable', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
                'branch' => ['nullable', 'string', 'max:160'],
                'upi_id' => ['nullable', 'regex:/^[a-z0-9._-]{2,}@[a-z0-9]{2,}$/'],
            ], [
                'account_number.regex' => 'An account number is 6 to 20 digits.',
                'ifsc.regex' => 'An IFSC code is 11 characters, e.g. HDFC0001234.',
                'upi_id.regex' => 'A UPI ID looks like name@bank.',
            ]);

            $hasBank = filled($data['account_number'] ?? null) && filled($data['ifsc'] ?? null) && filled($data['bank_name'] ?? null);
            if (! $hasBank && blank($data['upi_id'] ?? null)) {
                throw ValidationException::withMessages(['account_number' => 'Enter the bank name, account number and IFSC, or a UPI ID.']);
            }
        } else {
            $data = $request->validate($common + [
                'bank_name' => ['required', 'string', 'max:160'],
                'bank_address' => ['nullable', 'string', 'max:300'],
                'account_number' => ['nullable', 'regex:/^\d{4,30}$/'],
                'iban' => ['nullable', 'regex:/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/'],
                'swift' => ['nullable', 'regex:/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/'],
                'routing_number' => ['nullable', 'regex:/^\d{6,12}$/'],
                'sort_code' => ['nullable', 'regex:/^\d{2}-?\d{2}-?\d{2}$/'],
                'aba' => ['nullable', 'regex:/^\d{9}$/'],
                'intermediary_bank' => ['nullable', 'string', 'max:200'],
                'payment_provider' => ['nullable', 'string', 'max:80'],
                'payment_link' => ['nullable', 'url', 'max:300'],
            ], [
                'iban.regex' => 'That IBAN does not look right. It starts with two letters and two digits.',
                'swift.regex' => 'A SWIFT/BIC code is 8 or 11 letters and digits.',
                'aba.regex' => 'An ABA routing number is 9 digits.',
            ]);

            $hasAccount = filled($data['iban'] ?? null) || filled($data['account_number'] ?? null) || filled($data['payment_link'] ?? null) || filled($data['payment_provider'] ?? null);
            if (! $hasAccount) {
                throw ValidationException::withMessages(['iban' => 'Enter an IBAN or account number, or a payment link.']);
            }
        }

        $details = collect($data)->only(PayoutMethod::DETAIL_KEYS)->filter(fn ($v) => filled($v))->all();

        return [[
            'kind' => $kind,
            'type' => $kind === PayoutMethod::DOMESTIC ? (filled($data['account_number'] ?? null) ? 'bank_transfer' : 'upi') : (filled($data['payment_link'] ?? null) ? 'other' : 'bank_transfer'),
            'label' => $data['label'],
            'currency' => $data['currency'],
            'country_code' => isset($data['country_code']) ? strtoupper($data['country_code']) : ($kind === PayoutMethod::DOMESTIC ? 'IN' : null),
        ], $details];
    }
}
