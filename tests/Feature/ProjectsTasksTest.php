<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\File;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskRevision;
use Illuminate\Http\UploadedFile;

class ProjectsTasksTest extends WorkoraTestCase
{
    public function test_owner_creates_a_project_with_a_client_and_team(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->freelancer($owner);

        $this->actingAs($owner)->post(route('clients.store'), ['name' => 'Northwind'])->assertRedirect();
        $client = $this->inTenant($owner, fn () => Client::firstOrFail());

        $this->actingAs($owner)->post(route('projects.store'), [
            'name' => 'Brand Refresh', 'client_id' => $client->id, 'status' => 'active', 'currency' => 'INR', 'budget' => '50000', 'deadline' => now()->addMonth()->toDateString(),
        ])->assertRedirect();

        $project = $this->inTenant($owner, fn () => Project::firstOrFail());
        $this->assertSame(5000000, $project->budget_minor);
        $this->assertSame('brand-refresh', $project->slug);

        $this->actingAs($owner)->post(route('projects.members.add', $project), ['user_id' => $fiona->id, 'role_in_project' => 'Designer'])->assertRedirect();
        $this->actingAs($owner)->get(route('projects.show', $project))->assertOk()->assertSee('Brand Refresh')->assertSee('Fiona')->assertSee('Budget');
    }

    public function test_freelancers_see_only_their_projects_and_never_the_budget(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->freelancer($owner, 'Fiona');
        $mine = $this->projectFor($owner, [$fiona], ['name' => 'Fiona Project']);
        $other = $this->projectFor($owner, [], ['name' => 'Secret Project']);

        $this->actingAs($fiona)->get(route('projects.index'))->assertOk()->assertSee('Fiona Project')->assertDontSee('Secret Project');
        $this->actingAs($fiona)->get(route('projects.show', $other))->assertForbidden();

        $page = $this->actingAs($fiona)->get(route('projects.show', $mine))->assertOk();
        $page->assertDontSee('Cost so far')->assertDontSee('50,000.00');
        $this->actingAs($fiona)->get(route('projects.create'))->assertForbidden();
    }

    public function test_tasks_are_visible_only_to_their_assignees_among_freelancers(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->freelancer($owner, 'Fiona');
        $gus = $this->freelancer($owner, 'Gus');
        $project = $this->projectFor($owner, [$fiona, $gus]);
        $task = $this->taskFor($owner, $project, [$fiona], ['title' => 'Logo concepts']);

        $this->actingAs($fiona)->get(route('tasks.show', $task))->assertOk()->assertSee('Logo concepts');
        $this->actingAs($fiona)->get(route('tasks.index'))->assertSee('Logo concepts');

        $this->actingAs($gus)->get(route('tasks.show', $task))->assertForbidden();
        $this->actingAs($gus)->get(route('tasks.index'))->assertDontSee('Logo concepts');
        $this->actingAs($gus)->get(route('projects.show', $project))->assertOk()->assertDontSee('Logo concepts');
    }

    public function test_assigning_requires_project_membership_and_freelancers_cannot_create_tasks(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->freelancer($owner);
        $outsider = $this->freelancer($owner, 'Otto');
        $project = $this->projectFor($owner, [$fiona]);

        $this->actingAs($owner)->post(route('tasks.store', $project), ['title' => 'X', 'priority' => 'low', 'assignee_ids' => [$outsider->id]])->assertStatus(422);
        $this->actingAs($owner)->post(route('tasks.store', $project), ['title' => 'Real task', 'priority' => 'high', 'estimated_hours' => 4, 'assignee_ids' => [$fiona->id]])->assertRedirect();

        $task = $this->inTenant($owner, fn () => Task::where('title', 'Real task')->firstOrFail());
        $this->assertSame('assigned', $task->status);

        $this->actingAs($fiona)->post(route('tasks.store', $project), ['title' => 'Mine', 'priority' => 'low'])->assertForbidden();
    }

    public function test_the_full_work_cycle_start_submit_revise_resubmit_approve(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->freelancer($owner);
        $project = $this->projectFor($owner, [$fiona]);
        $task = $this->taskFor($owner, $project, [$fiona]);

        // Freelancer cannot approve their own work.
        $this->actingAs($fiona)->post(route('tasks.review', $task), ['decision' => 'approve'])->assertForbidden();

        $this->actingAs($fiona)->post(route('tasks.start', $task))->assertRedirect();
        $this->assertSame('in_progress', $task->fresh()->status);

        $this->actingAs($fiona)->post(route('tasks.submit', $task), ['note' => 'First pass done', 'files' => [UploadedFile::fake()->image('draft.png')]])->assertRedirect();
        $this->assertSame('submitted', $task->fresh()->status);
        $this->assertSame(1, File::withoutGlobalScopes()->where('original_name', 'draft.png')->count());

        // Asking for changes without saying what to change is rejected.
        $this->actingAs($owner)->post(route('tasks.review', $task), ['decision' => 'revise'])->assertSessionHasErrors('issues');
        $this->assertSame('submitted', $task->fresh()->status);

        // Reviewer asks for changes, one item per line.
        $this->actingAs($owner)->post(route('tasks.review', $task), ['decision' => 'revise', 'issues' => "Logo too small\nUse brand blue"])->assertRedirect();
        $this->assertSame('revision_required', $task->fresh()->status);
        $this->assertSame(2, TaskRevision::withoutGlobalScopes()->where('task_id', $task->id)->where('status', 'open')->count());

        // Fix and resubmit: the open items are resolved automatically.
        $this->actingAs($fiona)->post(route('tasks.submit', $task), ['note' => 'Fixed both'])->assertRedirect();
        $this->assertSame(0, TaskRevision::withoutGlobalScopes()->where('task_id', $task->id)->where('status', 'open')->count());

        $this->actingAs($owner)->post(route('tasks.review', $task), ['decision' => 'approve', 'note' => 'Great'])->assertRedirect();
        $fresh = $task->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame($owner->id, $fresh->approved_by);
        $this->assertSame(2, $task->submissions()->withoutGlobalScopes()->count());
        $this->assertTrue(AuditLog::withoutGlobalScopes()->where('action', 'task.approved')->exists());

        // Approved work cannot be submitted again or cancelled.
        $this->actingAs($fiona)->post(route('tasks.submit', $task), ['note' => 'again'])->assertStatus(422);
        $this->actingAs($owner)->post(route('tasks.cancel', $task))->assertStatus(422);
    }

    public function test_review_requires_a_submission(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->freelancer($owner);
        $task = $this->taskFor($owner, $this->projectFor($owner, [$fiona]), [$fiona]);

        $this->actingAs($owner)->post(route('tasks.review', $task), ['decision' => 'approve'])->assertStatus(422);
    }

    public function test_comments_checklists_and_attachments(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->freelancer($owner);
        $task = $this->taskFor($owner, $this->projectFor($owner, [$fiona]), [$fiona]);

        $this->actingAs($fiona)->post(route('tasks.comment', $task), ['body' => 'Question about colours'])->assertRedirect();
        $this->actingAs($fiona)->post(route('tasks.checklist.add', $task), ['title' => 'Pick palette'])->assertRedirect();
        $this->actingAs($fiona)->post(route('tasks.attach', $task), ['files' => [UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')]])->assertRedirect();

        $this->actingAs($owner)->get(route('tasks.show', $task))->assertOk()
            ->assertSee('Question about colours')->assertSee('Pick palette')->assertSee('brief.pdf');

        $item = $task->checklistItems()->withoutGlobalScopes()->firstOrFail();
        $this->actingAs($fiona)->patch(route('tasks.checklist.toggle', [$task, $item]))->assertRedirect();
        $this->assertTrue($item->fresh()->is_done);

        // Blocked file types are skipped quietly.
        $this->actingAs($fiona)->post(route('tasks.attach', $task), ['files' => [UploadedFile::fake()->create('evil.php', 1)]]);
        $this->assertSame(0, File::withoutGlobalScopes()->where('original_name', 'evil.php')->count());
    }

    public function test_another_company_cannot_touch_tasks_or_projects(): void
    {
        $alice = $this->userWithWorkspace('Alice');
        $bob = $this->userWithWorkspace('Bob');
        $project = $this->projectFor($alice);
        $task = $this->taskFor($alice, $project);

        $this->actingAs($bob)->get(route('projects.show', $project))->assertNotFound();
        $this->actingAs($bob)->get(route('tasks.show', $task))->assertNotFound();
        $this->actingAs($bob)->post(route('tasks.start', $task))->assertNotFound();
        $this->actingAs($bob)->get(route('projects.index'))->assertDontSee('Website');
    }

    public function test_freelancers_only_see_files_from_their_projects(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->freelancer($owner);
        $mine = $this->projectFor($owner, [$fiona]);
        $other = $this->projectFor($owner);

        $this->actingAs($owner)->postJson(route('files.store'), ['files' => [UploadedFile::fake()->create('visible.pdf', 5, 'application/pdf')], 'project_id' => $mine->id]);
        $this->actingAs($owner)->postJson(route('files.store'), ['files' => [UploadedFile::fake()->create('hidden.pdf', 5, 'application/pdf')], 'project_id' => $other->id]);

        $this->actingAs($fiona)->get(route('files.index'))->assertSee('visible.pdf')->assertDontSee('hidden.pdf');
        $hidden = File::withoutGlobalScopes()->where('original_name', 'hidden.pdf')->firstOrFail();
        $this->actingAs($fiona)->get(route('files.download', $hidden))->assertForbidden();
    }
}
