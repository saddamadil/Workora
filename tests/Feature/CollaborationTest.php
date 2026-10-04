<?php

namespace Tests\Feature;

use App\Models\ClientRequest;
use App\Models\File;
use App\Models\Message;
use App\Models\ProjectMilestone;
use App\Models\Task;
use Illuminate\Http\UploadedFile;

class CollaborationTest extends PortalTestCase
{
    public function test_messages_stay_in_their_project_and_unread_counts_work(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $seo = $this->makeProject($abc, $sam, 'SEO Campaign');
        $ads = $this->makeProject($abc, $sam, 'Google Ads');
        $alice = $this->portalUser($sam, $abc, 'Alice');

        $this->actingAs($sam)->post(route('messages.store'), ['client_id' => $abc->id, 'project_id' => $seo->id, 'body' => 'SEO report is ready https://example.com/report'])->assertRedirect();
        $this->actingAs($sam)->post(route('messages.store'), ['client_id' => $abc->id, 'project_id' => $ads->id, 'body' => 'Ads budget question'])->assertRedirect();

        // The client sees each thread on its own, with unread counts.
        $this->actingAs($alice)->get(route('portal.messages.index'))->assertOk()->assertSee('SEO Campaign')->assertDontSee('SEO report is ready');
        $this->actingAs($alice)->get(route('portal.messages.index', ['project' => $seo->id]))->assertOk()->assertSee('SEO report is ready')->assertSee('href="https://example.com/report"', false)->assertDontSee('Ads budget question');

        // Reading cleared that thread's unread count but not the other.
        $threads = app(\App\Services\Conversations::class);
        $this->inTenant($sam, function () use ($threads, $abc, $alice, $seo, $ads) {
            $t = $threads->threads($abc, $alice)->keyBy('key');
            $this->assertSame(0, $t[$seo->id]['unread']);
            $this->assertSame(1, $t[$ads->id]['unread']);
        });

        $this->actingAs($alice)->post(route('portal.messages.store'), ['project_id' => $seo->id, 'body' => 'Thanks, looks good'])->assertRedirect();
        $this->actingAs($sam)->get(route('messages.index', ['client' => $abc->id, 'project' => $seo->id]))->assertSee('Thanks, looks good');
        $this->actingAs($sam)->get(route('messages.index', ['client' => $abc->id, 'project' => $seo->id, 'q' => 'nothing-matches']))->assertDontSee('Thanks, looks good');

        // Poll returns json with the rendered list.
        $this->actingAs($alice)->getJson(route('portal.messages.poll', ['project' => $seo->id]))->assertOk()->assertJsonStructure(['last', 'html', 'typing', 'online'])->assertJsonPath('count', 2);

        // Notifications reached the other side.
        $this->assertTrue(\App\Models\AppNotification::withoutGlobalScopes()->where('user_id', $alice->id)->where('type', 'message')->exists());
    }

    public function test_one_client_cannot_read_or_write_into_another_clients_conversation(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $xyz = $this->makeClient($sam, 'XYZ Ltd', 'xyz@example.com');
        $xyzProject = $this->makeProject($xyz, $sam, 'XYZ Secret');
        $alice = $this->portalUser($sam, $abc, 'Alice');
        $this->actingAs($sam)->post(route('messages.store'), ['client_id' => $xyz->id, 'body' => 'XYZ PRIVATE TEXT'])->assertRedirect();

        $this->actingAs($alice)->get(route('portal.messages.index'))->assertDontSee('XYZ PRIVATE TEXT');
        $this->actingAs($alice)->get(route('portal.messages.index', ['project' => $xyzProject->id]))->assertNotFound();
        $this->actingAs($alice)->post(route('portal.messages.store'), ['project_id' => $xyzProject->id, 'body' => 'sneaky'])->assertNotFound();
        $this->actingAs($alice)->post(route('portal.messages.store'), ['client_id' => $xyz->id, 'body' => 'sneaky'])->assertRedirect(); // client_id is ignored; it posts to her own thread
        $this->assertSame(0, Message::withoutGlobalScopes()->where('client_id', $xyz->id)->where('user_id', $alice->id)->count());

        $msg = Message::withoutGlobalScopes()->where('client_id', $xyz->id)->firstOrFail();
        $this->actingAs($alice)->post(route('portal.messages.important', $msg))->assertNotFound();
        $this->actingAs($alice)->post(route('portal.messages.to-request', $msg), ['title' => 'x'])->assertNotFound();
    }

    public function test_message_attachments_and_conversion_to_task_and_request(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $seo = $this->makeProject($abc, $sam, 'SEO Campaign');
        $alice = $this->portalUser($sam, $abc, 'Alice');

        $this->actingAs($alice)->post(route('portal.messages.store'), ['project_id' => $seo->id, 'body' => 'Can you also research German keywords?', 'files' => [UploadedFile::fake()->create('brief.pdf', 20, 'application/pdf')]])->assertRedirect();
        $message = Message::withoutGlobalScopes()->firstOrFail();
        $file = File::withoutGlobalScopes()->where('attachable_id', $message->id)->firstOrFail();
        $this->assertTrue($file->visible_to_client);
        $this->actingAs($alice)->get(route('portal.files.show', $file))->assertOk();
        $this->actingAs($sam)->get(route('files.show', $file))->assertOk();

        // Staff: message to task, prefilled from the message.
        $this->actingAs($sam)->post(route('messages.to-task', $message), ['title' => 'German keyword research'])->assertRedirect();
        $task = Task::withoutGlobalScopes()->where('title', 'German keyword research')->firstOrFail();
        $this->assertSame($seo->id, $task->project_id);
        $this->assertSame('Can you also research German keywords?', $task->description);

        // Client: message to request.
        $this->actingAs($alice)->post(route('portal.messages.to-request', $message), ['title' => 'German keyword research'])->assertRedirect();
        $this->assertSame(1, ClientRequest::withoutGlobalScopes()->where('client_id', $abc->id)->count());

        // Important toggle.
        $this->actingAs($sam)->post(route('messages.important', $message))->assertRedirect();
        $this->assertTrue($message->fresh()->is_important);
    }

    public function test_files_are_private_until_shared_and_clients_can_upload(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $seo = $this->makeProject($abc, $sam, 'SEO Campaign');
        $xyz = $this->makeClient($sam, 'XYZ Ltd', 'xyz@example.com');
        $alice = $this->portalUser($sam, $abc, 'Alice');
        $xavier = $this->portalUser($sam, $xyz, 'Xavier');

        $this->actingAs($sam)->post(route('projects.files.store', $seo), ['files' => [UploadedFile::fake()->create('internal-notes.pdf', 10, 'application/pdf')], 'folder' => 'Research'])->assertRedirect();
        $this->actingAs($sam)->post(route('projects.files.store', $seo), ['files' => [UploadedFile::fake()->create('report.pdf', 10, 'application/pdf')], 'folder' => 'Reports', 'visible_to_client' => 1])->assertRedirect();
        $private = File::withoutGlobalScopes()->where('original_name', 'internal-notes.pdf')->firstOrFail();
        $shared = File::withoutGlobalScopes()->where('original_name', 'report.pdf')->firstOrFail();

        $this->actingAs($alice)->get(route('portal.files.index'))->assertOk()->assertSee('report.pdf')->assertDontSee('internal-notes.pdf');
        $this->actingAs($alice)->get(route('portal.files.show', $private))->assertNotFound();
        $this->actingAs($alice)->get(route('portal.files.download', $shared))->assertOk();
        $this->actingAs($xavier)->get(route('portal.files.show', $shared))->assertNotFound(); // another client

        // Share, then unshare.
        $this->actingAs($sam)->post(route('files.toggle-client', $private))->assertRedirect();
        $this->actingAs($alice)->get(route('portal.files.show', $private))->assertOk();
        $this->actingAs($sam)->post(route('files.toggle-client', $private))->assertRedirect();
        $this->actingAs($alice)->get(route('portal.files.show', $private))->assertNotFound();

        // A client's upload is visible to them and to the freelancer; blocked types are refused.
        $this->actingAs($alice)->post(route('portal.files.store'), ['files' => [UploadedFile::fake()->create('logo.png', 5, 'image/png')], 'project_id' => $seo->id])->assertRedirect();
        $up = File::withoutGlobalScopes()->where('original_name', 'logo.png')->firstOrFail();
        $this->assertSame($abc->id, $up->client_id);
        $this->actingAs($sam)->get(route('files.show', $up))->assertOk();
        $this->actingAs($alice)->post(route('portal.files.store'), ['files' => [UploadedFile::fake()->create('shell.php', 1)]])->assertSessionHas('error');
        $this->actingAs($alice)->post(route('portal.files.store'), ['files' => [UploadedFile::fake()->create('x.png', 1, 'image/png')], 'project_id' => $this->makeProject($xyz, $sam, 'XYZ')->id])->assertNotFound();

        // Clients cannot reach the freelancer's file routes.
        $this->actingAs($alice)->get(route('files.show', $private))->assertRedirect(route('portal.dashboard'));
    }

    public function test_milestones_progress_and_client_sees_them(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $seo = $this->makeProject($abc, $sam, 'SEO Campaign');
        $alice = $this->portalUser($sam, $abc, 'Alice');

        $this->actingAs($sam)->post(route('projects.milestones.store', $seo), ['title' => 'Technical audit', 'due_date' => now()->addWeek()->toDateString()])->assertRedirect();
        $m = ProjectMilestone::withoutGlobalScopes()->firstOrFail();
        $this->actingAs($sam)->patch(route('milestones.update', $m), ['status' => 'completed'])->assertRedirect();
        $this->assertNotNull($m->fresh()->completed_at);

        $this->actingAs($alice)->get(route('portal.project', [$seo->slug, 'tab' => 'milestones']))->assertOk()->assertSee('Technical audit')->assertSee('Completed');
        $this->actingAs($sam)->get(route('projects.show', [$seo, 'tab' => 'milestones']))->assertOk()->assertSee('Technical audit');
        $this->actingAs($alice)->patch(route('milestones.update', $m), ['status' => 'upcoming'])->assertRedirect(route('portal.dashboard'));
        $this->assertSame('completed', $m->fresh()->status);
    }

    public function test_work_requests_flow_from_client_to_task(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $seo = $this->makeProject($abc, $sam, 'SEO Campaign');
        $xyz = $this->makeClient($sam, 'XYZ Ltd', 'xyz@example.com');
        $alice = $this->portalUser($sam, $abc, 'Alice');
        $xavier = $this->portalUser($sam, $xyz, 'Xavier');

        $this->actingAs($alice)->post(route('portal.requests.store'), ['title' => 'Add FAQ schema', 'description' => 'For the blog', 'priority' => 'high', 'project_id' => $seo->id, 'preferred_deadline' => now()->addWeek()->toDateString()])->assertRedirect();
        $req = ClientRequest::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('new', $req->status);

        $this->actingAs($sam)->get(route('requests.index'))->assertOk()->assertSee('Add FAQ schema');
        $this->actingAs($xavier)->get(route('portal.requests.show', $req))->assertNotFound();
        $this->actingAs($xavier)->get(route('portal.requests.index'))->assertDontSee('Add FAQ schema');

        $this->actingAs($sam)->post(route('requests.respond', $req), ['status' => 'declined'])->assertSessionHasErrors('response_note');
        $this->actingAs($sam)->post(route('requests.respond', $req), ['status' => 'discussing', 'response_note' => 'Can we do it next week?'])->assertRedirect();
        $this->actingAs($alice)->get(route('portal.requests.show', $req))->assertOk()->assertSee('Can we do it next week?')->assertSee('Discussing');

        $this->actingAs($sam)->post(route('requests.to-task', $req), ['project_id' => $seo->id])->assertRedirect();
        $this->assertSame('accepted', $req->fresh()->status);
        $this->assertSame('high', Task::withoutGlobalScopes()->where('title', 'Add FAQ schema')->firstOrFail()->priority);
        $this->actingAs($alice)->get(route('portal.tasks'))->assertSee('Add FAQ schema');

        $this->actingAs($alice)->post(route('requests.respond', $req), ['status' => 'completed'])->assertRedirect(route('portal.dashboard'));
        $this->actingAs($alice)->post(route('portal.requests.store'), ['title' => 'x', 'priority' => 'low', 'project_id' => $this->makeProject($xyz, $sam, 'XYZ')->id])->assertNotFound();
    }

    public function test_internal_tasks_and_private_data_never_reach_the_portal(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $seo = $this->makeProject($abc, $sam, 'SEO Campaign');
        $alice = $this->portalUser($sam, $abc, 'Alice');
        $this->actingAs($sam)->post(route('tasks.store', $seo), ['title' => 'Visible task', 'priority' => 'medium'])->assertRedirect();
        $this->actingAs($sam)->post(route('tasks.store', $seo), ['title' => 'Secret internal task', 'priority' => 'medium'])->assertRedirect();
        Task::withoutGlobalScopes()->where('title', 'Secret internal task')->update(['is_internal' => true]);
        $this->actingAs($sam)->post(route('projects.files.store', $seo), ['files' => [UploadedFile::fake()->create('internal.pdf', 1, 'application/pdf')]]);

        $this->actingAs($alice)->get(route('portal.tasks'))->assertSee('Visible task')->assertDontSee('Secret internal task');
        $this->actingAs($alice)->get(route('portal.project', [$seo->slug, 'tab' => 'tasks']))->assertSee('Visible task')->assertDontSee('Secret internal task');
        $this->actingAs($alice)->get(route('portal.project', [$seo->slug, 'tab' => 'activity']))->assertDontSee('File uploaded');
    }

    public function test_every_project_tab_renders_for_the_freelancer(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $seo = $this->makeProject($abc, $sam, 'SEO Campaign');
        foreach (array_keys(\App\Http\Controllers\ProjectController::TABS) as $tab) {
            $this->actingAs($sam)->get(route('projects.show', [$seo, 'tab' => $tab]))->assertOk();
        }
        foreach (['overview', 'tasks', 'milestones', 'files', 'activity'] as $tab) {
            $alice ??= $this->portalUser($sam, $abc, 'Alice');
            $this->actingAs($alice)->get(route('portal.project', [$seo->slug, 'tab' => $tab]))->assertOk();
        }
        $this->actingAs($sam)->get(route('messages.index'))->assertOk();
        $this->actingAs($sam)->get(route('requests.index'))->assertOk();
    }
}
