<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\FreelancerProfile;
use App\Models\User;
use App\Services\Workspaces;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'That email and password do not match.']);
        }

        $request->session()->regenerate();
        $request->user()->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return $this->afterAuth($request);
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request, Workspaces $workspaces): RedirectResponse
    {
        $data = $request->validate([
            'account_type' => ['required', 'in:company,freelancer'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'workspace' => ['nullable', 'string', 'max:120'],
        ]);

        $invited = $request->session()->has('invite_token');

        $user = DB::transaction(function () use ($data, $workspaces, $invited) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            if ($invited) {
                // Joining through an invitation: the workspace they join is the one they were invited to.
                // A freelancer still gets their one profile, which follows them across companies.
                if ($data['account_type'] === 'freelancer') {
                    FreelancerProfile::create(['user_id' => $user->id]);
                }
            } elseif ($data['account_type'] === 'company') {
                $workspaces->createFor($user, ($data['workspace'] ?? null) ?: $data['name']."'s company");
            } else {
                // A freelancer on their own gets a workspace to run their business: clients, projects, invoices.
                FreelancerProfile::create(['user_id' => $user->id]);
                $workspaces->createFor($user, ($data['workspace'] ?? null) ?: $data['name'], 'solo');
            }

            return $user;
        });

        event(new \Illuminate\Auth\Events\Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        // A new solo freelancer is walked through setup; everyone else goes straight in.
        if (! $invited && $data['account_type'] === 'freelancer') {
            return redirect()->route('welcome')->with('status', 'Welcome to Freelancy.');
        }

        return $this->afterAuth($request)->with('status', 'Welcome to Freelancy.');
    }

    /** Pick up an invitation the person was looking at before they signed in, else go home. */
    private function afterAuth(Request $request): RedirectResponse
    {
        if ($token = $request->session()->pull('invite_token')) {
            return redirect()->route('invite.show', $token);
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
