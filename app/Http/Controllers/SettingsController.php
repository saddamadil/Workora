<?php

namespace App\Http\Controllers;

use App\Support\Money;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function company(Tenancy $tenancy): View
    {
        $this->authorize('manage-team');

        return view('settings.company', ['organization' => $tenancy->organization()]);
    }

    public function updateCompany(Request $request, Tenancy $tenancy): RedirectResponse
    {
        $this->authorize('manage-team');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'website' => ['nullable', 'url', 'max:200'],
            'address_line1' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'tax_identifier' => ['nullable', 'string', 'max:60'],
            'default_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'base_currency' => ['required', Rule::in(array_keys(Money::CURRENCIES))],
            'timezone' => ['required', 'timezone'],
        ]);

        $tenancy->organization()->update($data + ['default_tax_rate' => $data['default_tax_rate'] ?? 0]);

        return back()->with('status', 'Company settings saved.');
    }
}
