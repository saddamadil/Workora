<?php

namespace App\Http\Controllers;

use App\Services\ImageStore;
use App\Support\Money;
use App\Support\TaxFields;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class SettingsController extends Controller
{
    public const TEMPLATES = [
        'professional' => 'Professional', 'modern' => 'Modern', 'minimal' => 'Minimal',
        'international' => 'International', 'gst' => 'GST invoice',
    ];

    public const WIDGETS = [
        'kpis' => 'Key numbers (clients, projects, outstanding, this month)',
        'tasks' => "Today's tasks",
        'upcoming' => 'Upcoming deadlines and due dates',
        'invoices' => 'Recent invoices',
        'hours' => 'Work hours',
        'activity' => 'Recent activity',
        'quick' => 'Quick actions',
    ];

    /** A hub for personal settings, shared by freelancers and clients. */
    public function index(Request $request, Tenancy $tenancy): View
    {
        $user = $request->user();

        return view('settings.index', [
            'locales' => \App\Http\Middleware\SetLocale::SUPPORTED,
            'current' => $user->getAttributes()['locale'] ?? 'en',
            'widgets' => self::WIDGETS,
            'enabled' => $user->dashboard_widgets ?? array_keys(self::WIDGETS),
            'solo' => $tenancy->isSolo() && $tenancy->role() === \App\Enums\OrganizationRole::Owner,
        ]);
    }

    public function updateLocale(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Http\Middleware\SetLocale::SUPPORTED))]]);
        $request->user()->update(['locale' => $data['locale']]);

        return back()->with('status', 'Saved.');
    }

    public function updateWidgets(Request $request): RedirectResponse
    {
        $chosen = array_values(array_intersect(array_keys(self::WIDGETS), (array) $request->input('widgets', [])));
        $request->user()->update(['dashboard_widgets' => $chosen]);

        return back()->with('status', 'Dashboard updated.');
    }

    public function company(Tenancy $tenancy): View
    {
        $this->authorize('manage-team');

        return view('settings.company', ['organization' => $tenancy->organization(), 'templates' => self::TEMPLATES]);
    }

    public function updateCompany(Request $request, Tenancy $tenancy, ImageStore $images): RedirectResponse
    {
        $this->authorize('manage-team');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:200'],
            'address_line1' => ['nullable', 'string', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'default_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'base_currency' => ['required', Rule::in(array_keys(Money::CURRENCIES))],
            'timezone' => ['required', 'timezone'],
            'tax_ids' => ['nullable', 'array'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'invoice_template' => ['required', Rule::in(array_keys(self::TEMPLATES))],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:180'],
            'invoice_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        [$taxIds, $errors] = TaxFields::clean($data['country_code'] ?? null, $data['tax_ids'] ?? []);
        if ($errors) {
            throw ValidationException::withMessages(collect($errors)->mapWithKeys(fn ($m, $k) => ["tax_ids.$k" => $m])->all());
        }

        $org = $tenancy->organization();

        try {
            if ($request->hasFile('logo')) {
                $images->delete($org->logo_path);
                $org->logo_path = $images->store($request->file('logo'), 'branding/logos', 800);
            }
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['logo' => $e->getMessage()]);
        }

        $org->fill([
            'name' => $data['name'],
            'legal_name' => $data['legal_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'website' => $data['website'] ?? null,
            'address_line1' => $data['address_line1'] ?? null,
            'address_line2' => $data['address_line2'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'country_code' => isset($data['country_code']) ? strtoupper($data['country_code']) : null,
            'tax_ids' => $taxIds ?: null,
            'default_tax_rate' => $data['default_tax_rate'] ?? 0,
            'base_currency' => $data['base_currency'],
            'timezone' => $data['timezone'],
            'settings' => array_merge($org->settings ?? [], [
                'invoice_template' => $data['invoice_template'],
                'payment_terms_days' => (int) $data['payment_terms_days'],
                'invoice_notes' => $data['invoice_notes'] ?? null,
            ]),
        ])->save();

        return back()->with('status', 'Company profile saved.');
    }
}
