<?php

namespace App\Http\Controllers;

use App\Models\FreelancerProfile;
use App\Models\PayoutMethod;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Your own account, wherever you are signed in. Works with no company selected. */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('profile.edit', [
            'user' => $user,
            'profile' => FreelancerProfile::firstWhere('user_id', $user->id),
            'payout' => PayoutMethod::where('user_id', $user->id)->where('is_default', true)->first(),
            'timezones' => ['Asia/Kolkata', 'Asia/Dubai', 'Asia/Singapore', 'Europe/London', 'Europe/Berlin', 'America/New_York', 'America/Los_Angeles', 'Australia/Sydney', 'UTC'],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'timezone' => ['required', 'timezone'],
        ]);
        $user->update($data);

        if ($profile = FreelancerProfile::firstWhere('user_id', $user->id)) {
            $p = $request->validate([
                'headline' => ['nullable', 'string', 'max:160'],
                'bio' => ['nullable', 'string', 'max:3000'],
                'years_experience' => ['nullable', 'numeric', 'min:0', 'max:60'],
                'hourly_rate' => ['nullable', 'numeric', 'min:0'],
                'default_currency' => ['nullable', Rule::in(array_keys(Money::CURRENCIES))],
                'availability' => ['nullable', Rule::in(['available', 'limited', 'unavailable'])],
                'portfolio_url' => ['nullable', 'url', 'max:250'],
            ]);

            $profile->update([
                'headline' => $p['headline'] ?? null,
                'bio' => $p['bio'] ?? null,
                'years_experience' => $p['years_experience'] ?? null,
                'default_hourly_rate_minor' => Money::toMinor($p['hourly_rate'] ?? null),
                'default_currency' => $p['default_currency'] ?? 'INR',
                'availability' => $p['availability'] ?? 'available',
                'portfolio_url' => $p['portfolio_url'] ?? null,
            ]);

            $payout = $request->validate([
                'payout_type' => ['nullable', Rule::in(['bank_transfer', 'upi', 'paypal', 'wise', 'other'])],
                'payout_label' => ['nullable', 'string', 'max:120'],
                'payout_notes' => ['nullable', 'string', 'max:500'],
            ]);

            if (filled($payout['payout_label'] ?? null)) {
                PayoutMethod::updateOrCreate(['user_id' => $user->id, 'is_default' => true], [
                    'type' => $payout['payout_type'] ?? 'other',
                    'label' => $payout['payout_label'],
                    'currency' => $p['default_currency'] ?? 'INR',
                    'details_encrypted' => ['notes' => $payout['payout_notes'] ?? null],
                ]);
            }
        }

        return back()->with('status', 'Profile saved.');
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
