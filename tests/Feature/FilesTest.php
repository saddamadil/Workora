<?php

namespace Tests\Feature;

use App\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FilesTest extends WorkoraTestCase
{
    private function upload($user, UploadedFile ...$files)
    {
        return $this->actingAs($user)->postJson(route('files.store'), ['files' => $files]);
    }

    public function test_uploading_documents_and_images_stores_and_lists_them(): void
    {
        $user = $this->userWithWorkspace();

        $this->upload($user, UploadedFile::fake()->create('brief.pdf', 120, 'application/pdf'), UploadedFile::fake()->image('logo.png'))
            ->assertCreated()->assertJsonPath('uploaded', 2);

        $this->assertSame(2, File::withoutGlobalScopes()->count());
        $pdf = File::withoutGlobalScopes()->where('original_name', 'brief.pdf')->firstOrFail();
        $this->assertSame('Documents', $pdf->folder);
        $this->assertSame('Images', File::withoutGlobalScopes()->where('original_name', 'logo.png')->firstOrFail()->folder);
        Storage::disk(config('workora.disk'))->assertExists($pdf->path);

        $this->actingAs($user)->get(route('files.index'))->assertOk()->assertSee('brief.pdf')->assertSee('logo.png');
        $this->actingAs($user)->get(route('files.index', ['type' => 'images']))->assertSee('logo.png')->assertDontSee('brief.pdf');
        $this->actingAs($user)->get(route('files.index', ['q' => 'brief']))->assertSee('brief.pdf')->assertDontSee('logo.png');
    }

    public function test_dangerous_extensions_are_refused(): void
    {
        $user = $this->userWithWorkspace();

        $this->upload($user, UploadedFile::fake()->create('shell.php', 1))
            ->assertStatus(422)->assertJsonPath('skipped.0', 'shell.php');

        $this->assertSame(0, File::withoutGlobalScopes()->count());
    }

    public function test_oversized_files_are_rejected(): void
    {
        config(['workora.max_upload_mb' => 1]);
        $user = $this->userWithWorkspace();

        $this->upload($user, UploadedFile::fake()->create('big.zip', 2048))->assertStatus(422);
        $this->assertSame(0, File::withoutGlobalScopes()->count());
    }

    public function test_download_and_inline_view(): void
    {
        $user = $this->userWithWorkspace();
        $this->upload($user, UploadedFile::fake()->image('pic.png'), UploadedFile::fake()->create('notes.txt', 1, 'text/plain'));
        $pic = File::withoutGlobalScopes()->where('original_name', 'pic.png')->firstOrFail();
        $txt = File::withoutGlobalScopes()->where('original_name', 'notes.txt')->firstOrFail();

        $this->actingAs($user)->get(route('files.show', $pic))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($user)->get(route('files.download', $pic))->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=pic.png');
        // Non-previewable types are never rendered inline.
        $this->actingAs($user)->get(route('files.show', $txt))->assertHeader('content-disposition', 'attachment; filename=notes.txt');
    }

    public function test_svg_is_never_served_inline(): void
    {
        $user = $this->userWithWorkspace();
        $this->upload($user, UploadedFile::fake()->createWithContent('x.svg', '<svg onload="alert(1)"/>'));
        $svg = File::withoutGlobalScopes()->firstOrFail();
        $svg->update(['mime_type' => 'image/svg+xml']);

        $this->actingAs($user)->get(route('files.show', $svg))
            ->assertHeader('content-disposition', 'attachment; filename=x.svg');
    }

    public function test_another_workspace_cannot_see_or_touch_the_file(): void
    {
        $alice = $this->userWithWorkspace('Alice');
        $bob = $this->userWithWorkspace('Bob');
        $this->upload($alice, UploadedFile::fake()->create('secret.pdf', 10, 'application/pdf'));
        $file = File::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($bob)->get(route('files.show', $file))->assertNotFound();
        $this->actingAs($bob)->get(route('files.download', $file))->assertNotFound();
        $this->actingAs($bob)->delete(route('files.destroy', $file))->assertNotFound();
        $this->actingAs($bob)->postJson(route('files.share', $file))->assertNotFound();
        $this->actingAs($bob)->get(route('files.index'))->assertDontSee('secret.pdf');
    }

    public function test_rename_and_delete(): void
    {
        $user = $this->userWithWorkspace();
        $this->upload($user, UploadedFile::fake()->create('old.pdf', 10, 'application/pdf'));
        $file = File::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($user)->patch(route('files.update', $file), ['original_name' => '../../new name.pdf'])->assertRedirect();
        $this->assertSame('new name.pdf', $file->refresh()->original_name);

        $this->actingAs($user)->delete(route('files.destroy', $file))->assertRedirect(route('files.index'));
        $this->assertSoftDeleted($file);
    }

    public function test_viewers_can_read_but_not_upload_or_delete(): void
    {
        $owner = $this->userWithWorkspace('Alice');
        $this->upload($owner, UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'));
        $file = File::withoutGlobalScopes()->firstOrFail();

        $viewer = $this->userWithWorkspace('Vera', 'viewer');
        // Move the viewer into Alice's workspace.
        $viewer->memberships()->update(['organization_id' => $file->organization_id]);

        $this->actingAs($viewer)->get(route('files.index'))->assertOk()->assertSee('a.pdf')->assertDontSee('Drop files here');
        $this->upload($viewer, UploadedFile::fake()->create('b.pdf', 1))->assertForbidden();
        $this->actingAs($viewer)->delete(route('files.destroy', $file))->assertForbidden();
        $this->actingAs($viewer)->postJson(route('files.share', $file))->assertForbidden();
    }
}
