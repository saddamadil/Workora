<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(): View
    {
        $this->authorize('staff');

        return view('clients.index', ['clients' => Client::query()->withCount('projects')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-clients');
        Client::create($this->validated($request));

        return back()->with('status', 'Client added.');
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('manage-clients');
        $client->update($this->validated($request));

        return back()->with('status', 'Client saved.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('manage-clients');
        $client->delete();

        return back()->with('status', 'Client removed. Their projects are kept.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
