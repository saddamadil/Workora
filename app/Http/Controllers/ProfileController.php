<?php

namespace App\Http\Controllers;

use App\Models\FreelancerProfile;
use App\Services\ImageStore;
use App\Support\Money;
use App\Support\TaxFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/** Your own account, wherever you are signed in. Works with no company selected. */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('profile.edit', [
            'user' => $user,
            'profile' => FreelancerProfile::firstWhere('user_id', $user->id),
            'timezones' => ['Asia/Kolkata', 'Asia/Dubai', 'Asia/Singapore', 'Europe/London', 'Europe/Berlin', 'America/New_York', 'America/Los_Angeles', 'Australia/Sydney', 'UTC'],
        ]);
    }

    public function update(Request $request, ImageStore $images): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'timezone' => ['required', 'timezone'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);

        try {
            if ($request->hasFile('photo')) {
                $images->delete($user->avatar_path);
                $data['avatar_path'] = $images->store($request->file('photo'), 'branding/avatars', 600);
            }
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['photo' => $e->getMessage()]);
        }

        unset($data['photo']);
        $user->update($data);

        if ($profile = FreelancerProfile::firstWhere('user_id', $user->id)) {
            $this->updateFreelancer($request, $profile, $images);
        }

        return back()->with('status', 'Profile saved.');
    }

    private function updateFreelancer(Request $request, FreelancerProfile $profile, ImageStore $images): void
    {
        $p = $request->validate([
            'headline' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:3000'],
            'years_experience' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'default_currency' => ['nullable', Rule::in(array_keys(Money::CURRENCIES))],
            'availability' => ['nullable', Rule::in(['available', 'limited', 'unavailable'])],
            'portfolio_url' => ['nullable', 'url', 'max:250'],
            'website' => ['nullable', 'url', 'max:250'],
            'linkedin_url' => ['nullable', 'url', 'max:250'],
            'address_line1' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'invoice_prefix' => ['nullable', 'string', 'max:6', 'regex:/^[A-Za-z0-9]*$/'],
            'signature' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'tax_ids' => ['nullable', 'array'],
        ]);

        [$taxIds, $errors] = TaxFields::clean($p['country_code'] ?? null, $p['tax_ids'] ?? []);
        if ($errors) {
            throw ValidationException::withMessages(collect($errors)->mapWithKeys(fn ($m, $k) => ["tax_ids.$k" => $m])->all());
        }

        $update = [
            'headline' => $p['headline'] ?? null,
            'bio' => $p['bio'] ?? null,
            'years_experience' => $p['years_experience'] ?? null,
            'default_hourly_rate_minor' => Money::toMinor($p['hourly_rate'] ?? null),
            'default_currency' => $p['default_currency'] ?? 'INR',
            'availability' => $p['availability'] ?? 'available',
            'portfolio_url' => $p['portfolio_url'] ?? null,
            'website' => $p['website'] ?? null,
            'linkedin_url' => $p['linkedin_url'] ?? null,
            'address_line1' => $p['address_line1'] ?? null,
            'city' => $p['city'] ?? null,
            'state' => $p['state'] ?? null,
            'postal_code' => $p['postal_code'] ?? null,
            'country_code' => isset($p['country_code']) ? strtoupper($p['country_code']) : null,
            'tax_ids' => $taxIds ?: null,
            'invoice_prefix' => strtoupper($p['invoice_prefix'] ?? '') ?: 'INV',
        ];

        try {
            if ($request->hasFile('signature')) {
                $images->delete($profile->signature_path);
                $update['signature_path'] = $images->store($request->file('signature'), 'branding/signatures', 600);
            }
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['signature' => $e->getMessage()]);
        }

        $profile->update($update);
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'Password changed.');
    }
}
