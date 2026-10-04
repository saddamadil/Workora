<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Support\Facades\DB;

class SecurityTest extends PortalTestCase
{
    public function test_totp_matches_the_rfc_6238_test_vector(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // "12345678901234567890"
        $this->assertSame('287082', Totp::code($secret, 59));
        $this->assertSame('081804', Totp::code($secret, 1111111109));
        $this->assertTrue(Totp::verify($secret, '287082', 59));
        $this->assertTrue(Totp::verify($secret, '287 082', 70), 'allows one step of drift and ignores spaces');
        $this->assertFalse(Totp::verify($secret, '287082', 500));
        $this->assertFalse(Totp::verify($secret, 'abcdef', 59));
    }

    public function test_turning_on_two_factor_changes_how_signing_in_works(): void
    {
        $sam = $this->solo();

        $this->actingAs($sam)->post(route('security.start'))->assertRedirect();
        $this->actingAs($sam)->get(route('security.index'))->assertOk()->assertSee('QR code');
        $secret = session('two_factor.setup');
        $this->actingAs($sam)->post(route('security.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->actingAs($sam)->post(route('security.confirm'), ['code' => Totp::code($secret)])->assertRedirect(route('security.index'));
        $sam->refresh();
        $this->assertNotNull($sam->two_factor_confirmed_at);
        $codes = json_decode($sam->two_factor_recovery_codes, true);
        $this->assertCount(8, $codes);
        $this->actingAs($sam)->get(route('security.index'))->assertSee($codes[0]);
        $this->assertNotSame($secret, $sam->getRawOriginal('two_factor_secret'), 'the secret is stored encrypted');

        $this->flushSession(); $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => $sam->email, 'password' => 'secret-pass-1'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->post(route('two-factor.verify'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post(route('two-factor.verify'), ['code' => Totp::code($secret)])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($sam);

        // A recovery code works once.
        $this->flushSession(); $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => $sam->email, 'password' => 'secret-pass-1']);
        $this->post(route('two-factor.verify'), ['code' => $codes[0]])->assertRedirect(route('dashboard'));
        $this->assertCount(7, json_decode($sam->fresh()->two_factor_recovery_codes, true), 'a used code is gone');
        $this->assertNotContains($codes[0], json_decode($sam->fresh()->two_factor_recovery_codes, true));

        // Turning it off needs the password.
        $this->actingAs($sam)->post(route('security.disable'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->actingAs($sam)->post(route('security.disable'), ['password' => 'secret-pass-1'])->assertRedirect();
        $this->assertNull($sam->fresh()->two_factor_confirmed_at);
        $this->flushSession(); $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => $sam->email, 'password' => 'secret-pass-1'])->assertRedirect(route('dashboard'));
    }

    public function test_the_challenge_cannot_be_reached_or_reused_without_a_password_step(): void
    {
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
        $this->post(route('two-factor.verify'), ['code' => '123456'])->assertRedirect(route('login'));
    }

    public function test_signing_out_other_devices_keeps_only_this_one(): void
    {
        config(['session.driver' => 'database']);
        $sam = $this->solo();
        $other = $this->solo('Other');
        $row = fn ($id, $user) => ['id' => $id, 'user_id' => $user, 'ip_address' => '1.2.3.4', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120', 'payload' => '', 'last_activity' => time()];
        DB::table('sessions')->insert([$row('s-phone', $sam->id), $row('s-laptop', $sam->id), $row('s-theirs', $other->id)]);

        $this->actingAs($sam)->get(route('security.index'))->assertOk()->assertSee('Chrome on Windows');
        $this->actingAs($sam)->post(route('security.sign-out-others'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertTrue(DB::table('sessions')->where('id', 's-phone')->exists());
        $this->actingAs($sam)->post(route('security.sign-out-others'), ['password' => 'secret-pass-1'])->assertRedirect();
        $this->assertFalse(DB::table('sessions')->where('id', 's-phone')->exists());
        $this->assertFalse(DB::table('sessions')->where('id', 's-laptop')->exists());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count(), "someone else's sessions are untouched");
    }

    public function test_clients_can_use_security_too(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $alice = $this->portalUser($sam, $abc, 'Alice');
        $this->actingAs($alice)->get(route('security.index'))->assertOk()->assertSee('Two-factor');
    }
}
