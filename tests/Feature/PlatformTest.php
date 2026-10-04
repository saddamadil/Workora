<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FreelancerProfile;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

class PlatformTest extends PortalTestCase
{
    public function test_reports_add_up_per_currency_and_render_in_every_range(): void
    {
        $sam = $this->solo();
        $sam->freelancerProfile->update(['default_hourly_rate_minor' => 100000]);
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $seo = $this->makeProject($abc, $sam, 'SEO Campaign');
        $paid = $this->sentInvoice($sam, $abc, '3000', project: $seo);
        $this->sentInvoice($sam, $abc, '1000', project: $seo);
        $this->actingAs($sam)->post(route('invoices.mark-paid', $paid), ['method' => 'upi', 'paid_on' => now()->toDateString()]);
        $this->actingAs($sam)->post(route('time.store'), ['project_id' => $seo->id, 'entry_date' => now()->toDateString(), 'duration' => '2'])->assertRedirect();

        foreach (['month', 'quarter', 'year', 'all'] as $range) {
            $this->actingAs($sam)->get(route('reports.index', ['range' => $range]))->assertOk();
        }
        $this->actingAs($sam)->get(route('reports.index', ['range' => 'month']))->assertOk()
            ->assertSee('3,000.00')->assertSee('1,000.00')->assertSee('ABC GmbH')->assertSee('SEO Campaign')->assertSee('Project profitability');

        $alice = $this->portalUser($sam, $abc, 'Alice');
        $this->actingAs($alice)->get(route('reports.index'))->assertRedirect(route('portal.dashboard'));
    }

    public function test_search_finds_only_what_the_person_may_see(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $xyz = $this->makeClient($sam, 'XYZ Ltd', 'xyz@example.com');
        $seo = $this->makeProject($abc, $sam, 'Alpha Campaign');
        $secret = $this->makeProject($xyz, $sam, 'Alpha Secret');
        $alice = $this->portalUser($sam, $abc, 'Alice');
        $this->actingAs($sam)->post(route('messages.store'), ['client_id' => $xyz->id, 'body' => 'alpha confidential note'])->assertRedirect();
        $this->actingAs($sam)->post(route('tasks.store', $seo), ['title' => 'Alpha public task', 'priority' => 'medium']);
        $this->actingAs($sam)->post(route('tasks.store', $seo), ['title' => 'Alpha private task', 'priority' => 'medium', 'is_internal' => 1]);
        $this->actingAs($sam)->post(route('projects.files.store', $seo), ['files' => [UploadedFile::fake()->create('alpha-private.pdf', 1, 'application/pdf')]]);

        $titles = fn ($res) => collect($res->json('groups'))->flatMap(fn ($g) => collect($g['items'])->pluck('title'))->all();

        $this->actingAs($sam)->getJson(route('search.index', ['q' => 'alpha']))->assertOk();
        $staff = $titles($this->actingAs($sam)->getJson(route('search.index', ['q' => 'alpha'])));
        foreach (['Alpha Campaign', 'Alpha Secret', 'Alpha public task', 'Alpha private task', 'alpha-private.pdf'] as $t) {
            $this->assertContains($t, $staff);
        }
        $this->assertSame([], $this->actingAs($sam)->getJson(route('search.index', ['q' => 'a']))->json('groups'), 'one letter is too short');

        $client = $titles($this->actingAs($alice)->getJson(route('search.index', ['q' => 'alpha'])));
        $this->assertContains('Alpha Campaign', $client);
        $this->assertContains('Alpha public task', $client);
        foreach (['Alpha Secret', 'Alpha private task', 'alpha-private.pdf', 'alpha confidential note'] as $leak) {
            $this->assertNotContains($leak, $client, "$leak must not reach the client");
        }
    }

    public function test_client_company_page_updates_only_their_own_record(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $xyz = $this->makeClient($sam, 'XYZ Ltd', 'xyz@example.com');
        $this->inTenant($sam, fn () => $abc->update(['notes' => 'PRIVATE', 'status' => 'active']));
        $alice = $this->portalUser($sam, $abc, 'Alice');

        $this->actingAs($alice)->get(route('portal.company'))->assertOk()->assertSee('ABC GmbH')->assertDontSee('PRIVATE');
        $this->actingAs($alice)->post(route('portal.company.update'), [
            'name' => 'ABC GmbH', 'legal_name' => 'ABC Gesellschaft mbH', 'country_code' => 'DE', 'city' => 'Berlin', 'default_currency' => 'EUR',
            'address' => 'Hauptstr. 1', 'tax_ids' => ['vat' => 'DE123456789'], 'status' => 'inactive', 'notes' => 'HACKED', 'id' => $xyz->id,
        ])->assertRedirect();
        $abc = Client::withoutGlobalScopes()->findOrFail($abc->id);
        $this->assertSame('ABC Gesellschaft mbH', $abc->legal_name);
        $this->assertSame('EUR', $abc->default_currency);
        $this->assertSame('PRIVATE', $abc->notes);
        $this->assertSame('active', $abc->status);
        $this->assertSame('XYZ Ltd', Client::withoutGlobalScopes()->findOrFail($xyz->id)->name);

        // The details flow onto the next invoice.
        $invoice = $this->sentInvoice($sam, $abc);
        $this->actingAs($alice)->get(route('invoices.preview', $invoice))->assertSee('Hauptstr. 1')->assertSee('Berlin');
    }

    public function test_settings_language_widgets_and_help(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $alice = $this->portalUser($sam, $abc, 'Alice');

        $this->actingAs($sam)->get(route('settings.index'))->assertOk()->assertSee('Dashboard widgets');
        $this->actingAs($alice)->get(route('settings.index'))->assertOk()->assertDontSee('Dashboard widgets')->assertSee('Notifications');
        $this->actingAs($sam)->get(route('help'))->assertOk()->assertSee('How do I invite a client?');
        $this->actingAs($alice)->get(route('help'))->assertOk()->assertSee('How do I approve work?');

        $this->actingAs($sam)->post(route('settings.locale'), ['locale' => 'xx'])->assertSessionHasErrors('locale');
        $this->actingAs($sam)->post(route('settings.locale'), ['locale' => 'de'])->assertRedirect();
        $this->actingAs($sam)->get(route('dashboard'))->assertOk()->assertSee('Meine Kunden')->assertSee('Einstellungen');
        $this->actingAs($sam)->post(route('settings.locale'), ['locale' => 'ar'])->assertRedirect();
        $this->actingAs($sam)->get(route('dashboard'))->assertSee('dir="rtl"', false);
        $this->actingAs($sam)->post(route('settings.locale'), ['locale' => 'en'])->assertRedirect();

        $this->actingAs($sam)->get(route('dashboard'))->assertSee("Today's tasks", false)->assertSee('Active clients');
        $this->actingAs($sam)->post(route('settings.widgets'), ['widgets' => ['tasks']])->assertRedirect();
        $this->actingAs($sam)->get(route('dashboard'))->assertSee("Today's tasks", false)->assertDontSee('Active clients')->assertDontSee('Quick actions');
    }

    public function test_onboarding_wizard_creates_the_first_client_and_project_and_can_be_skipped(): void
    {
        $this->post('/register', ['account_type' => 'freelancer', 'name' => 'Fay Free', 'email' => 'fay@example.com', 'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1'])->assertRedirect(route('welcome'));

        foreach ([1, 2, 3, 4, 5, 6] as $step) {
            $this->get(route('welcome', $step))->assertOk();
        }
        $this->post(route('welcome.profile'), ['headline' => 'SEO consultant', 'country_code' => 'IN', 'city' => 'Pune'])->assertRedirect(route('welcome', 3));
        $this->assertSame('SEO consultant', FreelancerProfile::query()->firstOrFail()->headline);

        $this->post(route('welcome.payment'), ['account_holder' => 'Fay', 'upi_id' => 'fay@okbank', 'currency' => 'INR', 'country_code' => 'IN'])->assertRedirect(route('welcome', 4));
        $this->post(route('welcome.payment'), ['account_holder' => 'Fay', 'ifsc' => 'BAD', 'bank_name' => 'B', 'account_number' => '123456', 'currency' => 'INR'])->assertSessionHasErrors('ifsc');

        $this->post(route('welcome.client'), ['name' => 'ABC GmbH', 'email' => 'abc@example.com', 'type' => 'company'])->assertRedirect();
        $client = Client::withoutGlobalScopes()->firstOrFail();
        $this->post(route('welcome.client'), ['name' => 'ABC GmbH', 'type' => 'company'])->assertRedirect()->assertSessionHas('duplicate');
        $this->assertSame(1, Client::withoutGlobalScopes()->count());

        $this->post(route('welcome.project'), ['name' => 'SEO Campaign', 'client_id' => $client->id])->assertRedirect(route('welcome', 6));
        $this->assertSame($client->id, Project::withoutGlobalScopes()->firstOrFail()->client_id);

        $this->post(route('welcome.done'))->assertRedirect(route('dashboard'));
        $user = User::where('email', 'fay@example.com')->firstOrFail();
        $this->assertTrue((bool) (\App\Models\Organization::findOrFail($user->memberships()->first()->organization_id)->settings['onboarded'] ?? false));
    }

    public function test_onboarding_is_only_for_solo_workspaces(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $this->actingAs($owner)->get(route('welcome'))->assertForbidden();
    }

    public function test_password_reset_and_email_verification(): void
    {
        Notification::fake();
        $user = User::create(['name' => 'Pat', 'email' => 'pat@example.com', 'password' => 'old-password-1']);

        // Same answer whether or not the account exists.
        $this->post(route('password.email'), ['email' => 'nobody@example.com'])->assertSessionHas('status');
        Notification::assertNothingSent();
        $this->post(route('password.email'), ['email' => 'pat@example.com'])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use ($user) {
            $this->get(route('password.reset', ['token' => $n->token, 'email' => $user->email]))->assertOk();
            $this->post(route('password.update'), ['token' => 'wrong', 'email' => $user->email, 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertSessionHasErrors('email');
            $this->post(route('password.update'), ['token' => $n->token, 'email' => $user->email, 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertRedirect(route('login'));

            return true;
        });
        $this->post('/login', ['email' => 'pat@example.com', 'password' => 'new-password-1'])->assertRedirect();
        $this->assertAuthenticated();
        auth()->logout();

        // Registration sends a verification link; following it verifies, but nothing is blocked before.
        $this->post('/register', ['account_type' => 'freelancer', 'name' => 'Vera', 'email' => 'vera@example.com', 'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1']);
        $vera = User::where('email', 'vera@example.com')->firstOrFail();
        Notification::assertSentTo($vera, VerifyEmail::class);
        $this->actingAs($vera)->get(route('dashboard'))->assertOk()->assertSee('Please verify your email address');
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $vera->id, 'hash' => sha1($vera->email)]);
        $this->actingAs($vera)->get($url)->assertRedirect(route('dashboard'));
        $this->assertNotNull($vera->fresh()->email_verified_at);
        $this->actingAs($vera)->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $vera->id, 'hash' => 'bad']))->assertForbidden();
    }

    public function test_images_are_served_only_to_the_people_who_should_see_them(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $xyz = $this->makeClient($sam, 'XYZ Ltd', 'xyz@example.com');
        $alice = $this->portalUser($sam, $abc, 'Alice');
        $xavier = $this->portalUser($sam, $xyz, 'Xavier');

        $this->actingAs($alice)->post(route('portal.company.update'), ['name' => 'ABC GmbH', 'logo' => UploadedFile::fake()->image('logo.png', 200, 100)])->assertSessionHasNoErrors();
        $this->actingAs($xavier)->post(route('profile.update'), ['name' => 'Xavier', 'timezone' => 'UTC'])->assertStatus(302);
        \App\Models\User::query()->whereKey($xavier->id)->update(['avatar_path' => 'branding/avatars/fake.png']);
        \Illuminate\Support\Facades\Storage::disk(config('workora.disk'))->put('branding/avatars/fake.png', 'x');

        $this->actingAs($alice)->get(route('assets.client-logo', $abc))->assertOk();
        $this->actingAs($xavier)->get(route('assets.client-logo', $abc))->assertNotFound();
        $this->actingAs($sam)->get(route('assets.client-logo', $abc))->assertOk();
        $this->actingAs($alice)->get(route('assets.avatar', $xavier))->assertNotFound();
    }

    public function test_notification_links_never_leave_the_site(): void
    {
        $sam = $this->solo();
        foreach (['https://evil.example/phish' => false, '//evil.example/x' => false, '/clients' => true] as $url => $ok) {
            $n = \App\Models\AppNotification::withoutGlobalScopes()->create(['organization_id' => $this->orgOf($sam)->id, 'user_id' => $sam->id, 'type' => 'message', 'title' => 't', 'url' => $url, 'created_at' => now()]);
            $res = $this->actingAs($sam)->get(route('notifications.open', $n));
            $ok ? $res->assertRedirect('/clients') : $res->assertRedirect(route('notifications.index'));
        }
    }
}
