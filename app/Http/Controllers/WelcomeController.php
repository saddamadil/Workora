<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\FreelancerProfile;
use App\Models\PayoutMethod;
use App\Models\Project;
use App\Services\ImageStore;
use App\Support\Countries;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** First-run setup for a freelancer: profile, payment details, first client, first project. Every step can be skipped. */
class WelcomeController extends Controller
{
    public const STEPS = [1 => 'Welcome', 2 => 'Your profile', 3 => 'Get paid', 4 => 'First client', 5 => 'First project', 6 => 'Ready'];

    public function __construct(private Tenancy $tenancy) {}

    private function guard(): void
    {
        abort_unless($this->tenancy->isSolo() && $this->tenancy->role() === \App\Enums\OrganizationRole::Owner, 403);
    }

    public function show(Request $request, int $step = 1): View
    {
        $this->guard();
        $step = max(1, min(6, $step));

        return view('welcome-setup', [
            'step' => $step, 'steps' => self::STEPS, 'countries' => Countries::LIST,
            'profile' => FreelancerProfile::query()->where('user_id', $request->user()->id)->first(),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'email']),
            'client' => $request->filled('client') ? Client::query()->find($request->query('client')) : null,
            'currencies' => array_keys(\App\Support\Money::CURRENCIES),
        ]);
    }

    public function profile(Request $request): RedirectResponse
    {
        $this->guard();
        $data = $request->validate([
            'headline' => ['nullable', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:100'],
        ]);
        FreelancerProfile::query()->where('user_id', $request->user()->id)->update(array_filter([
            'headline' => $data['headline'] ?? null, 'city' => $data['city'] ?? null,
            'country_code' => isset($data['country_code']) ? strtoupper($data['country_code']) : null,
        ], fn ($v) => $v !== null && $v !== ''));

        return redirect()->route('welcome', 3);
    }

    /** Uses the same checks as the full payment profile form. */
    public function payment(Request $request): RedirectResponse
    {
        $this->guard();
        $request->merge(['kind' => 'domestic', 'label' => $request->input('label') ?: 'Main account']);
        app(PaymentProfileController::class)->store($request);

        return redirect()->route('welcome', 4);
    }

    public function client(Request $request, ImageStore $images): RedirectResponse
    {
        $this->guard();
        $response = app(ClientController::class)->store($request, $images);
        if (session()->has('duplicate')) {
            return back()->withInput();
        }
        $client = Client::query()->where('name', $request->input('name'))->latest()->firstOrFail();

        return redirect()->route('welcome', ['step' => 5, 'client' => $client->id]);
    }

    public function project(Request $request): RedirectResponse
    {
        $this->guard();
        $request->merge(['status' => 'active', 'currency' => $request->input('currency') ?: $this->tenancy->organization()->base_currency]);
        app(ProjectController::class)->store($request);

        return redirect()->route('welcome', 6);
    }

    public function done(): RedirectResponse
    {
        $this->guard();
        $org = $this->tenancy->organization();
        $org->update(['settings' => array_merge($org->settings ?? [], ['onboarded' => true])]);

        return redirect()->route('dashboard')->with('status', 'You are set up.');
    }
}
