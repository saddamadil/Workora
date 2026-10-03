<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Services\ImageStore;
use App\Support\Money;
use App\Support\TaxFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class ClientController extends Controller
{
    public function index(): View
    {
        $this->authorize('staff');

        return view('clients.index', ['clients' => Client::query()->withCount('projects')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        $this->authorize('manage-clients');

        return view('clients.form', ['client' => new Client(['default_currency' => app(\App\Support\Tenancy::class)->organization()->base_currency])]);
    }

    public function show(Client $client): View
    {
        $this->authorize('staff');

        return view('clients.show', [
            'client' => $client->loadCount('projects'),
            'invoices' => Gate::allows('see-money')
                ? Invoice::query()->with('freelancer:id,name')->where('client_id', $client->id)->latest('issue_date')->limit(10)->get()
                : collect(),
        ]);
    }

    public function edit(Client $client): View
    {
        $this->authorize('manage-clients');

        return view('clients.form', ['client' => $client]);
    }

    public function store(Request $request, ImageStore $images): RedirectResponse
    {
        $this->authorize('manage-clients');
        $client = Client::create($this->validated($request, $images));

        return redirect()->route('clients.show', $client)->with('status', 'Client added.');
    }

    public function update(Request $request, Client $client, ImageStore $images): RedirectResponse
    {
        $this->authorize('manage-clients');
        $client->update($this->validated($request, $images, $client));

        return redirect()->route('clients.show', $client)->with('status', 'Client saved.');
    }

    public function destroy(Client $client, ImageStore $images): RedirectResponse
    {
        $this->authorize('manage-clients');
        $client->delete();

        return redirect()->route('clients.index')->with('status', 'Client removed. Their projects and past invoices are kept.');
    }

    private function validated(Request $request, ImageStore $images, ?Client $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'default_currency' => ['nullable', Rule::in(array_keys(Money::CURRENCIES))],
            'payment_method' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'tax_ids' => ['nullable', 'array'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);

        [$taxIds, $errors] = TaxFields::clean($data['country_code'] ?? null, $data['tax_ids'] ?? []);
        if ($errors) {
            throw ValidationException::withMessages(collect($errors)->mapWithKeys(fn ($m, $k) => ["tax_ids.$k" => $m])->all());
        }

        $data['tax_ids'] = $taxIds ?: null;
        $data['country_code'] = isset($data['country_code']) ? strtoupper($data['country_code']) : null;

        try {
            if ($request->hasFile('logo')) {
                $images->delete($existing?->logo_path);
                $data['logo_path'] = $images->store($request->file('logo'), 'branding/clients', 600);
            }
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['logo' => $e->getMessage()]);
        }
        unset($data['logo']);

        return $data;
    }
}
