<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Email verification is encouraged, never required: a site without working email must still be usable. */
class VerifyEmailController extends Controller
{
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = $request->user();
        abort_unless(hash_equals((string) $user->getKey(), $id) && hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect()->route('dashboard')->with('status', 'Email verified. Thank you.');
    }

    public function resend(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            try {
                $request->user()->sendEmailVerificationNotification();
            } catch (\Throwable) {
                return back()->with('error', 'We could not send the email right now. Please try again later.');
            }
        }

        return back()->with('status', 'Verification email sent.');
    }
}
