<?php

namespace Tests\Feature;

use App\Models\File;
use Illuminate\Http\UploadedFile;

class FileVersionTest extends PortalTestCase
{
    public function test_new_versions_replace_in_listings_but_keep_history_and_sharing(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $seo = $this->makeProject($abc, $sam, 'SEO Campaign');
        $alice = $this->portalUser($sam, $abc, 'Alice');
        $this->actingAs($sam)->post(route('projects.files.store', $seo), ['files' => [UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')], 'folder' => 'Client Brand Kit', 'visible_to_client' => 1])->assertRedirect();
        $v1 = File::withoutGlobalScopes()->where('original_name', 'brief.pdf')->firstOrFail();
        $this->assertSame('Client Brand Kit', $v1->folder, 'custom folder names are allowed');

        $this->actingAs($sam)->post(route('files.version', $v1), ['file' => UploadedFile::fake()->create('brief-final.pdf', 12, 'application/pdf')])->assertRedirect();
        $v2 = File::withoutGlobalScopes()->where('replaces_file_id', $v1->id)->firstOrFail();
        $this->assertSame(2, $v2->version);
        $this->assertTrue($v2->visible_to_client);
        $this->assertSame('Client Brand Kit', $v2->folder);
        $this->assertSame([$v2->id, $v1->id], $v2->history()->pluck('id')->all());

        $this->actingAs($sam)->get(route('projects.show', [$seo, 'tab' => 'files']))->assertOk()->assertSee('brief-final.pdf')->assertDontSee('>brief.pdf<', false)->assertSee('Earlier versions');
        $this->actingAs($alice)->get(route('portal.files.index'))->assertOk()->assertSee('brief-final.pdf')->assertDontSee('>brief.pdf<', false);

        // Only the newest version can be replaced; blocked types are refused.
        $this->actingAs($sam)->post(route('files.version', $v1), ['file' => UploadedFile::fake()->create('again.pdf', 1, 'application/pdf')])->assertStatus(422);
        $this->actingAs($sam)->post(route('files.version', $v2), ['file' => UploadedFile::fake()->create('x.php', 1)])->assertStatus(422);

        // Moving to another folder.
        $this->actingAs($sam)->patch(route('files.update', $v2), ['folder' => 'Archive'])->assertRedirect();
        $this->assertSame('Archive', $v2->refresh()->folder);

        $other = $this->solo('Other');
        $this->actingAs($other)->post(route('files.version', $v2), ['file' => UploadedFile::fake()->create('z.pdf', 1, 'application/pdf')])->assertNotFound();
    }
}
