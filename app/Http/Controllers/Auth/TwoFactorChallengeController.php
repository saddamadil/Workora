<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** The second step of signing in, for people who turned two-factor on. */
class TwoFactorChallengeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        return $this->pending($request) ? view('auth.two-factor') : redirect()->route('login');
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $this->pending($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'Your sign-in took too long. Please start again.']);
        }

        $data = $request->validate(['code' => ['required', 'string', 'max:30']]);
        $input = trim($data['code']);
        $ok = Totp::verify((string) $user->two_factor_secret, $input);

        if (! $ok) {
            // A recovery code works once, then is used up.
            $codes = json_decode((string) $user->two_factor_recovery_codes, true) ?: [];
            $match = collect($codes)->first(fn ($c) => hash_equals($c, strtolower($input)));
            if ($match) {
                $user->forceFill(['two_factor_recovery_codes' => json_encode(array_values(array_diff($codes, [$match])))])->save();
                $ok = true;
            }
        }

        if (! $ok) {
            return back()->withErrors(['code' => 'That code is not right.']);
        }

        $remember = (bool) $request->session()->pull('two_factor.remember');
        $request->session()->forget(['two_factor.user', 'two_factor.at']);
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        if ($token = $request->session()->pull('invite_token')) {
            return redirect()->route('invite.show', $token);
        }

        return redirect()->intended(route('dashboard'));
    }

    private function pending(Request $request): ?User
    {
        $id = $request->session()->get('two_factor.user');
        $at = (int) $request->session()->get('two_factor.at');
        if (! $id || now()->timestamp - $at > 600) {
            return null;
        }

        return User::query()->find($id);
    }
}
