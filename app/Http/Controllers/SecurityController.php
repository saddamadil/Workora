<?php

namespace App\Http\Controllers;

use App\Support\Totp;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/** Two-factor sign-in and the list of places a person is signed in. Available to every kind of login. */
class SecurityController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $setup = $request->session()->get('two_factor.setup');

        return view('security.index', [
            'enabled' => $user->two_factor_confirmed_at !== null,
            'setupSecret' => $setup,
            'qr' => $setup ? (new QRCode(new QROptions(['outputInterface' => QRGdImagePNG::class, 'outputBase64' => true, 'scale' => 5, 'quietzoneSize' => 1])))->render(Totp::uri($setup, $user->email)) : null,
            'codes' => $request->session()->pull('two_factor.codes'),
            'sessions' => $this->sessions($request),
            'tracked' => config('session.driver') === 'database',
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        abort_if($request->user()->two_factor_confirmed_at !== null, 422, 'Two-factor sign-in is already on.');
        $request->session()->put('two_factor.setup', Totp::secret());

        return redirect()->route('security.index');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $secret = $request->session()->get('two_factor.setup');
        if (! $secret || ! Totp::verify($secret, $data['code'])) {
            return back()->withErrors(['code' => 'That code is not right. Check the time on your phone and try again.']);
        }

        $codes = Totp::recoveryCodes();
        $request->user()->forceFill(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => json_encode($codes), 'two_factor_confirmed_at' => now()])->save();
        $request->session()->forget('two_factor.setup');
        $request->session()->put('two_factor.codes', $codes);
        $this->signOutOthers($request);

        return redirect()->route('security.index')->with('status', 'Two-factor sign-in is on. Save your recovery codes now.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $this->checkPassword($request);
        $request->user()->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();

        return back()->with('status', 'Two-factor sign-in is off.');
    }

    public function recoveryCodes(Request $request): RedirectResponse
    {
        $this->checkPassword($request);
        abort_unless($request->user()->two_factor_confirmed_at !== null, 422);
        $codes = Totp::recoveryCodes();
        $request->user()->forceFill(['two_factor_recovery_codes' => json_encode($codes)])->save();
        $request->session()->put('two_factor.codes', $codes);

        return redirect()->route('security.index');
    }

    public function signOutOtherDevices(Request $request): RedirectResponse
    {
        $this->checkPassword($request);
        $n = $this->signOutOthers($request);

        return back()->with('status', $n ? "Signed out of {$n} other ".str('device')->plural($n).'.' : 'You were not signed in anywhere else.');
    }

    private function checkPassword(Request $request): void
    {
        $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($request->input('password'), $request->user()->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['password' => 'That password is not right.']);
        }
    }

    private function signOutOthers(Request $request): int
    {
        if (config('session.driver') !== 'database') {
            return 0;
        }

        return DB::table(config('session.table', 'sessions'))->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
    }

    private function sessions(Request $request): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        return DB::table(config('session.table', 'sessions'))->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get()->map(fn ($s) => [
            'current' => $s->id === $request->session()->getId(), 'ip' => $s->ip_address, 'agent' => $this->agent((string) $s->user_agent), 'when' => \Illuminate\Support\Carbon::createFromTimestamp($s->last_activity),
        ])->all();
    }

    private function agent(string $ua): string
    {
        $browser = match (true) {
            str_contains($ua, 'Edg') => 'Edge', str_contains($ua, 'OPR') || str_contains($ua, 'Opera') => 'Opera', str_contains($ua, 'Chrome') => 'Chrome', str_contains($ua, 'Firefox') => 'Firefox', str_contains($ua, 'Safari') => 'Safari', default => 'Browser',
        };
        $os = match (true) {
            str_contains($ua, 'Windows') => 'Windows', str_contains($ua, 'Android') => 'Android', str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS', str_contains($ua, 'Mac OS') => 'macOS', str_contains($ua, 'Linux') => 'Linux', default => 'unknown system',
        };

        return $browser.' on '.$os;
    }
}
