<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\ShareLink;
use Illuminate\Http\UploadedFile;

class ShareLinkTest extends WorkoraTestCase
{
    private function sharedFile(array $options = []): array
    {
        $user = $this->userWithWorkspace();
        $this->actingAs($user)->postJson(route('files.store'), ['files' => [UploadedFile::fake()->image('photo.png')]]);
        $file = File::withoutGlobalScopes()->firstOrFail();

        $response = $this->actingAs($user)->postJson(route('files.share', $file), $options)->assertCreated();
        auth()->logout();

        return [$user, $file, ShareLink::firstOrFail(), $response->json('url')];
    }

    public function test_anyone_with_the_link_can_view_and_download_without_signing_in(): void
    {
        [, , $link, $url] = $this->sharedFile();

        $this->assertStringContainsString($link->token, $url);
        $this->get($url)->assertOk()->assertSee('photo.png')->assertSee('Download');
        $this->get(route('share.preview', $link->token))->assertOk();
        $this->get(route('share.download', $link->token))->assertOk()->assertHeader('content-disposition', 'attachment; filename=photo.png');

        $link->refresh();
        $this->assertSame(1, $link->view_count);
        $this->assertSame(1, $link->download_count);
    }

    public function test_unknown_token_is_404(): void
    {
        $this->get(route('share.show', 'nope'))->assertNotFound();
    }

    public function test_password_protection(): void
    {
        [, , $link] = $this->sharedFile(['password' => 'open-sesame']);

        $this->assertNotSame('open-sesame', $link->password);
        $this->get(route('share.show', $link->token))->assertOk()->assertSee('This file is protected');
        $this->get(route('share.download', $link->token))->assertForbidden();
        $this->get(route('share.preview', $link->token))->assertForbidden();

        $this->post(route('share.unlock', $link->token), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->get(route('share.download', $link->token))->assertForbidden();

        $this->post(route('share.unlock', $link->token), ['password' => 'open-sesame'])->assertRedirect();
        $this->get(route('share.show', $link->token))->assertOk()->assertSee('photo.png');
        $this->get(route('share.download', $link->token))->assertOk();
    }

    public function test_expired_links_stop_working(): void
    {
        [, , $link] = $this->sharedFile(['expires_in_days' => 1]);

        $this->get(route('share.show', $link->token))->assertOk();

        $this->travel(2)->days();
        $this->get(route('share.show', $link->token))->assertStatus(410)->assertSee('expired');
        $this->get(route('share.download', $link->token))->assertStatus(410);
    }

    public function test_download_limit_is_enforced(): void
    {
        [, , $link] = $this->sharedFile(['max_downloads' => 1]);

        $this->get(route('share.download', $link->token))->assertOk();
        $this->get(route('share.download', $link->token))->assertStatus(410);
        $this->get(route('share.show', $link->token))->assertStatus(410)->assertSee('download limit');
    }

    public function test_owner_can_turn_a_link_off(): void
    {
        [$user, , $link] = $this->sharedFile();

        $this->actingAs($user)->get(route('shares.index'))->assertOk()->assertSee('photo.png')->assertSee('Active');
        $this->actingAs($user)->delete(route('shares.destroy', $link->id))->assertRedirect();
        auth()->logout();

        $this->get(route('share.show', $link->token))->assertStatus(410)->assertSee('turned this link off');
    }

    public function test_deleting_the_file_kills_its_links(): void
    {
        [$user, $file, $link] = $this->sharedFile();

        $this->actingAs($user)->delete(route('files.destroy', $file));
        auth()->logout();

        $this->get(route('share.show', $link->token))->assertStatus(410);
        $this->get(route('share.download', $link->token))->assertStatus(410);
    }

    public function test_another_workspace_cannot_revoke_my_link(): void
    {
        [, , $link] = $this->sharedFile();
        $bob = $this->userWithWorkspace('Bob');

        $this->actingAs($bob)->delete(route('shares.destroy', $link->id))->assertNotFound();
        $this->assertNull($link->refresh()->revoked_at);
    }

    public function test_share_options_are_validated(): void
    {
        $user = $this->userWithWorkspace();
        $this->actingAs($user)->postJson(route('files.store'), ['files' => [UploadedFile::fake()->image('p.png')]]);
        $file = File::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($user)->postJson(route('files.share', $file), ['expires_in_days' => 999])->assertStatus(422);
        $this->actingAs($user)->postJson(route('files.share', $file), ['password' => 'abc'])->assertStatus(422);
    }
}
