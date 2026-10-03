<?php

namespace Tests\Feature;

use App\Models\DriveConnection;
use App\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class GoogleDriveTest extends WorkoraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'cid', 'services.google.client_secret' => 'secret']);
    }

    private function connected()
    {
        $user = $this->userWithWorkspace();
        DriveConnection::create([
            'user_id' => $user->id, 'google_email' => 'alice@gmail.com',
            'access_token' => 'at', 'refresh_token' => 'rt', 'expires_at' => now()->addHour(),
        ]);

        return $user;
    }

    public function test_shows_setup_help_when_not_configured(): void
    {
        config(['services.google.client_id' => null]);
        $user = $this->userWithWorkspace();

        $this->actingAs($user)->get(route('drive.index'))->assertOk()
            ->assertSee('not set up on this server')->assertSee(route('drive.callback'), false);
        $this->actingAs($user)->get(route('drive.connect'))->assertNotFound();
    }

    public function test_connect_redirects_to_google_with_state_and_scopes(): void
    {
        $user = $this->userWithWorkspace();

        $response = $this->actingAs($user)->get(route('drive.connect'));
        $response->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');
        $location = $response->headers->get('Location');
        parse_str(parse_url($location, PHP_URL_QUERY), $q);

        $this->assertSame('cid', $q['client_id']);
        $this->assertSame(route('drive.callback'), $q['redirect_uri']);
        $this->assertSame(session('drive_oauth_state'), $q['state']);
        $this->assertStringContainsString('drive.readonly', $q['scope']);
    }

    public function test_callback_rejects_a_bad_state(): void
    {
        $user = $this->userWithWorkspace();

        $this->actingAs($user)->withSession(['drive_oauth_state' => 'right'])
            ->get(route('drive.callback', ['state' => 'wrong', 'code' => 'x']))
            ->assertRedirect(route('drive.index'))->assertSessionHas('error');

        $this->assertSame(0, DriveConnection::count());
    }

    public function test_callback_stores_encrypted_tokens(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'AT', 'refresh_token' => 'RT', 'expires_in' => 3600]),
            'openidconnect.googleapis.com/*' => Http::response(['email' => 'alice@gmail.com']),
        ]);
        $user = $this->userWithWorkspace();

        $this->actingAs($user)->withSession(['drive_oauth_state' => 'abc'])
            ->get(route('drive.callback', ['state' => 'abc', 'code' => 'the-code']))
            ->assertRedirect(route('drive.index'))->assertSessionHas('status');

        $connection = DriveConnection::firstOrFail();
        $this->assertSame('AT', $connection->access_token);
        $this->assertSame('alice@gmail.com', $connection->google_email);
        $this->assertNotSame('AT', $connection->getRawOriginal('access_token'));
    }

    public function test_browsing_lists_drive_files(): void
    {
        Http::fake(['www.googleapis.com/drive/v3/files*' => Http::response(['files' => [
            ['id' => 'f1', 'name' => 'Reports', 'mimeType' => 'application/vnd.google-apps.folder'],
            ['id' => 'f2', 'name' => 'plan.pdf', 'mimeType' => 'application/pdf', 'size' => '2048'],
        ]])]);

        $this->actingAs($this->connected())->get(route('drive.index'))->assertOk()
            ->assertSee('Reports')->assertSee('plan.pdf')->assertSee('alice@gmail.com');
    }

    public function test_import_copies_a_drive_file_into_the_workspace(): void
    {
        Http::fake([
            'www.googleapis.com/drive/v3/files/f2?fields*' => Http::response(['id' => 'f2', 'name' => 'plan.pdf', 'mimeType' => 'application/pdf', 'size' => '11']),
            'www.googleapis.com/drive/v3/files/f2?alt=media' => Http::response('hello world'),
        ]);
        $user = $this->connected();

        $this->actingAs($user)->post(route('drive.import'), ['ids' => ['f2']])
            ->assertRedirect(route('files.index'))->assertSessionHas('status');

        $file = File::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('plan.pdf', $file->original_name);
        $this->assertSame('google_drive', $file->source);
        $this->assertSame('f2', $file->drive_file_id);
        $this->assertSame('hello world', \Storage::disk($file->disk)->get($file->path));
    }

    public function test_google_docs_are_exported_to_office_formats(): void
    {
        Http::fake([
            'www.googleapis.com/drive/v3/files/d1?fields*' => Http::response(['id' => 'd1', 'name' => 'Proposal', 'mimeType' => 'application/vnd.google-apps.document']),
            'www.googleapis.com/drive/v3/files/d1/export*' => Http::response('DOCX-BYTES'),
        ]);

        $this->actingAs($this->connected())->post(route('drive.import'), ['ids' => ['d1']]);

        $this->assertSame('Proposal.docx', File::withoutGlobalScopes()->firstOrFail()->original_name);
    }

    public function test_import_skips_folders_and_oversized_files_with_a_reason(): void
    {
        config(['workora.max_upload_mb' => 1]);
        Http::fake([
            'www.googleapis.com/drive/v3/files/a?fields*' => Http::response(['id' => 'a', 'name' => 'Folder', 'mimeType' => 'application/vnd.google-apps.folder']),
            'www.googleapis.com/drive/v3/files/b?fields*' => Http::response(['id' => 'b', 'name' => 'huge.mov', 'mimeType' => 'video/quicktime', 'size' => (string) (5 * 1024 * 1024)]),
        ]);

        $this->actingAs($this->connected())->post(route('drive.import'), ['ids' => ['a', 'b']])
            ->assertRedirect(route('drive.index'))->assertSessionHas('error');

        $this->assertSame(0, File::withoutGlobalScopes()->count());
    }

    public function test_expired_token_is_refreshed(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'NEW', 'expires_in' => 3600]),
            'www.googleapis.com/drive/v3/files*' => Http::response(['files' => []]),
        ]);
        $user = $this->connected();
        DriveConnection::firstOrFail()->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($user)->get(route('drive.index'))->assertOk();

        $this->assertSame('NEW', DriveConnection::firstOrFail()->access_token);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer NEW'));
    }

    public function test_revoked_grant_drops_the_connection(): void
    {
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);
        $user = $this->connected();
        DriveConnection::firstOrFail()->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($user)->get(route('drive.index'))->assertOk()->assertSee('Connect with Google');
        $this->assertSame(0, DriveConnection::count());
    }

    public function test_export_uploads_a_workspace_file_to_drive(): void
    {
        Http::fake(['www.googleapis.com/upload/drive/v3/files*' => Http::response(['id' => 'new1', 'name' => 'brief.pdf'])]);
        $user = $this->connected();
        $this->actingAs($user)->postJson(route('files.store'), ['files' => [UploadedFile::fake()->create('brief.pdf', 5, 'application/pdf')]]);
        $file = File::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($user)->post(route('files.drive', $file))->assertSessionHas('status');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'uploadType=multipart')
            && str_contains($r->body(), '"name":"brief.pdf"')
            && str_starts_with($r->header('Content-Type')[0], 'multipart/related'));
    }

    public function test_export_without_a_connection_points_to_connect(): void
    {
        $user = $this->userWithWorkspace();
        $this->actingAs($user)->postJson(route('files.store'), ['files' => [UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')]]);

        $this->actingAs($user)->post(route('files.drive', File::withoutGlobalScopes()->firstOrFail()))
            ->assertRedirect(route('drive.index'))->assertSessionHas('error');
    }

    public function test_disconnect_removes_the_connection(): void
    {
        Http::fake();
        $user = $this->connected();

        $this->actingAs($user)->post(route('drive.disconnect'))->assertRedirect(route('drive.index'));
        $this->assertSame(0, DriveConnection::count());
    }
}
