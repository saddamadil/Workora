<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\CalendarEvent;
use App\Models\Deliverable;
use App\Models\Invoice;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;

class WorkflowTest extends PortalTestCase
{
    public function test_deliverable_approval_and_revision_cycle_updates_the_project(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $xyz = $this->makeClient($sam, 'XYZ Ltd', 'xyz@example.com');
        $seo = $this->makeProject($abc, $sam, 'SEO Campaign');
        $alice = $this->portalUser($sam, $abc, 'Alice');
        $xavier = $this->portalUser($sam, $xyz, 'Xavier');
        $this->actingAs($sam)->post(route('projects.milestones.store', $seo), ['title' => 'Keyword research'])->assertRedirect();
        $ms = ProjectMilestone::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($sam)->post(route('projects.deliverables.store', $seo), ['title' => 'Keyword report', 'milestone_id' => $ms->id, 'files' => [UploadedFile::fake()->create('report.pdf', 5, 'application/pdf')]])->assertRedirect();
        $d = Deliverable::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('review', $seo->fresh()->status);
        $this->actingAs($alice)->get(route('portal.project', $seo->slug))->assertOk()->assertSee('Keyword report')->assertSee('Approve')->assertSee('Request changes');
        $this->actingAs($xavier)->post(route('portal.deliverables.approve', $d))->assertNotFound();

        // Changes need a comment; they put the project into "revision requested".
        $this->actingAs($alice)->post(route('portal.deliverables.changes', $d), [])->assertSessionHasErrors('comment');
        $this->actingAs($alice)->post(route('portal.deliverables.changes', $d), ['comment' => 'Add German terms', 'files' => [UploadedFile::fake()->create('notes.pdf', 2, 'application/pdf')]])->assertRedirect();
        $this->assertSame('changes_requested', $d->fresh()->status);
        $this->assertSame('revision_requested', $seo->fresh()->status);
        $this->assertTrue(AppNotification::withoutGlobalScopes()->where('user_id', $sam->id)->where('type', 'revision')->exists());

        // A decided deliverable cannot be decided again until it is resubmitted.
        $this->actingAs($alice)->post(route('portal.deliverables.approve', $d))->assertStatus(422);
        $this->actingAs($sam)->post(route('deliverables.resubmit', $d))->assertRedirect();
        $this->assertSame('review', $seo->fresh()->status);

        $this->actingAs($alice)->post(route('portal.deliverables.approve', $d), ['comment' => 'Great'])->assertRedirect();
        $this->assertSame('approved', $d->fresh()->status);
        $this->assertSame('active', $seo->fresh()->status);
        $this->assertSame('completed', $ms->fresh()->status, 'approving a deliverable completes its milestone');
        $this->assertSame(2, $d->reviews()->withoutGlobalScopes()->count(), 'both decisions are kept');
        $this->actingAs($sam)->get(route('projects.show', [$seo, 'tab' => 'milestones']))->assertSee('Round 1')->assertSee('Add German terms');
        $this->actingAs($sam)->post(route('portal.deliverables.approve', $d))->assertForbidden();
    }

    public function test_notifications_list_open_and_preferences(): void
    {
        Mail::fake();
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $alice = $this->portalUser($sam, $abc, 'Alice');

        $invoice = $this->sentInvoice($sam, $abc, '2500');
        $n = AppNotification::withoutGlobalScopes()->where('user_id', $alice->id)->where('type', 'invoice')->firstOrFail();
        $this->actingAs($alice)->get(route('notifications.index'))->assertOk()->assertSee('New invoice '.$invoice->number);
        $this->actingAs($alice)->get(route('portal.dashboard'))->assertSee('Notifications');
        $this->actingAs($alice)->get(route('notifications.open', $n))->assertRedirect(route('portal.invoice', $invoice));
        $this->assertNotNull($n->fresh()->read_at);
        $this->actingAs($sam)->get(route('notifications.open', $n))->assertNotFound();

        // Payment recorded tells the client.
        $this->actingAs($sam)->post(route('invoices.mark-paid', $invoice), ['method' => 'bank_transfer', 'paid_on' => now()->toDateString()])->assertRedirect();
        $this->assertTrue(AppNotification::withoutGlobalScopes()->where('user_id', $alice->id)->where('type', 'payment')->exists());

        // Turning a kind off in-app stops it being stored; the settings page works for a client.
        $this->actingAs($alice)->get(route('notifications.preferences'))->assertOk();
        $this->actingAs($alice)->post(route('notifications.preferences.save'), ['prefs' => ['message' => ['app' => 0, 'email' => 0], 'invoice' => ['app' => 1, 'email' => 0]]])->assertRedirect();
        $before = AppNotification::withoutGlobalScopes()->where('user_id', $alice->id)->where('type', 'message')->count();
        $this->actingAs($sam)->post(route('messages.store'), ['client_id' => $abc->id, 'body' => 'hello'])->assertRedirect();
        $this->assertSame($before, AppNotification::withoutGlobalScopes()->where('user_id', $alice->id)->where('type', 'message')->count());

        $this->actingAs($alice)->post(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, AppNotification::withoutGlobalScopes()->where('user_id', $alice->id)->whereNull('read_at')->count());
    }

    public function test_due_reminders_are_sent_once(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $alice = $this->portalUser($sam, $abc, 'Alice');
        $invoice = $this->sentInvoice($sam, $abc);
        Invoice::withoutGlobalScopes()->whereKey($invoice->id)->update(['due_date' => now()->subDays(2)->toDateString()]);

        $this->artisan('reminders:due')->assertSuccessful();
        $this->artisan('reminders:due')->assertSuccessful();
        $this->assertSame(1, AppNotification::withoutGlobalScopes()->where('user_id', $alice->id)->where('title', 'like', 'Overdue:%')->count());
        $this->assertSame(1, AppNotification::withoutGlobalScopes()->where('user_id', $sam->id)->where('title', 'like', 'Overdue:%')->count());
        $this->actingAs($alice)->get(route('portal.invoices'))->assertSee('Overdue');
    }

    public function test_calendar_shows_dates_to_the_right_people_only(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $xyz = $this->makeClient($sam, 'XYZ Ltd', 'xyz@example.com');
        $seo = $this->makeProject($abc, $sam, 'SEO Campaign');
        $secret = $this->makeProject($xyz, $sam, 'XYZ Secret');
        $alice = $this->portalUser($sam, $abc, 'Alice');
        $soon = now()->addDays(3)->toDateString();

        $this->actingAs($sam)->post(route('projects.milestones.store', $seo), ['title' => 'Audit', 'due_date' => $soon]);
        $this->actingAs($sam)->post(route('projects.milestones.store', $secret), ['title' => 'Secret stage', 'due_date' => $soon]);
        $this->actingAs($sam)->post(route('tasks.store', $seo), ['title' => 'Public task', 'priority' => 'medium', 'due_at' => $soon]);
        $this->actingAs($sam)->post(route('tasks.store', $seo), ['title' => 'Private task', 'priority' => 'medium', 'due_at' => $soon]);
        Task::withoutGlobalScopes()->where('title', 'Private task')->update(['is_internal' => true]);
        $this->actingAs($sam)->post(route('calendar.events.store'), ['title' => 'Kickoff call', 'kind' => 'meeting', 'date' => $soon, 'time' => '10:00', 'client_id' => $abc->id, 'visible_to_client' => 1])->assertRedirect();
        $this->actingAs($sam)->post(route('calendar.events.store'), ['title' => 'Private reminder', 'kind' => 'reminder', 'date' => $soon, 'client_id' => $abc->id])->assertRedirect();
        $this->actingAs($sam)->post(route('calendar.events.store'), ['title' => 'XYZ meeting', 'kind' => 'meeting', 'date' => $soon, 'client_id' => $xyz->id, 'visible_to_client' => 1])->assertRedirect();

        $this->actingAs($sam)->get(route('calendar.index', ['view' => 'agenda']))->assertOk()->assertSee('Audit')->assertSee('Secret stage')->assertSee('Private task')->assertSee('Kickoff call');
        foreach (['month', 'week', 'day'] as $v) {
            $this->actingAs($sam)->get(route('calendar.index', ['view' => $v, 'date' => $soon]))->assertOk();
        }

        $this->actingAs($alice)->get(route('portal.calendar.index', ['view' => 'agenda']))->assertOk()
            ->assertSee('Audit')->assertSee('Public task')->assertSee('Kickoff call')
            ->assertDontSee('Secret stage')->assertDontSee('Private task')->assertDontSee('Private reminder')->assertDontSee('XYZ meeting');
        $this->actingAs($alice)->post(route('calendar.events.store'), ['title' => 'x', 'kind' => 'meeting', 'date' => $soon])->assertRedirect(route('portal.dashboard'));

        $event = CalendarEvent::withoutGlobalScopes()->where('title', 'Kickoff call')->firstOrFail();
        $this->actingAs($alice)->delete(route('calendar.events.destroy', $event))->assertRedirect(route('portal.dashboard'));
        $this->actingAs($sam)->delete(route('calendar.events.destroy', $event))->assertRedirect();
        $this->assertSame(0, CalendarEvent::withoutGlobalScopes()->where('title', 'Kickoff call')->count());
    }

    public function test_solo_work_log_flows_into_a_project_invoice_without_timesheet_approval(): void
    {
        $sam = $this->solo();
        $sam->freelancerProfile->update(['default_hourly_rate_minor' => 100000]);
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $xyz = $this->makeClient($sam, 'XYZ Ltd', 'xyz@example.com');
        $seo = $this->makeProject($abc, $sam, 'SEO Campaign');
        $other = $this->makeProject($xyz, $sam, 'XYZ Work');

        $this->actingAs($sam)->post(route('time.store'), ['project_id' => $seo->id, 'entry_date' => now()->toDateString(), 'duration' => '2:30', 'description' => 'Keyword research'])->assertRedirect();
        $this->actingAs($sam)->post(route('time.store'), ['project_id' => $seo->id, 'entry_date' => now()->toDateString(), 'duration' => '1', 'is_billable' => 0])->assertRedirect();
        $this->actingAs($sam)->post(route('time.store'), ['project_id' => $other->id, 'entry_date' => now()->toDateString(), 'duration' => '4', 'rate' => '500'])->assertRedirect();
        $entry = TimeEntry::withoutGlobalScopes()->where('description', 'Keyword research')->firstOrFail();
        $this->assertSame($abc->id, $entry->client_id);
        $this->assertSame(100000, $entry->rate_minor);

        $this->actingAs($sam)->get(route('time.index'))->assertOk()->assertSee('so far')->assertDontSee('Submit week');
        $this->actingAs($sam)->get(route('projects.show', [$seo, 'tab' => 'worklog']))->assertOk()->assertSee('Keyword research');

        $invoice = $this->sentInvoice($sam, $abc, '1', send: false, project: $seo);
        $this->assertSame($seo->id, $invoice->project_id);
        $this->actingAs($sam)->post(route('invoices.import-time', $invoice))->assertRedirect();
        $invoice->refresh();
        $this->assertSame(250000 + 100, $invoice->subtotal_minor, '2.5 billable hours at 1,000.00 plus the 1.00 line; non-billable and the other client are left out');
        $this->actingAs($sam)->get(route('projects.show', [$seo, 'tab' => 'invoices']))->assertSee($invoice->number);

        // A project of another client cannot be put on this invoice.
        $this->actingAs($sam)->post(route('invoices.store'), [
            'bill_to_type' => 'client', 'client_id' => $abc->id, 'project_id' => $other->id, 'issue_date' => now()->toDateString(), 'terms_days' => '14',
            'currency' => 'INR', 'invoice_type' => 'domestic', 'template' => 'professional', 'tax_treatment' => 'none',
        ])->assertNotFound();
    }

    public function test_solo_task_completion_notifies_the_client_unless_private(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $seo = $this->makeProject($abc, $sam, 'SEO Campaign');
        $alice = $this->portalUser($sam, $abc, 'Alice');
        $this->actingAs($sam)->post(route('tasks.store', $seo), ['title' => 'Public task', 'priority' => 'medium']);
        $this->actingAs($sam)->post(route('tasks.store', $seo), ['title' => 'Private task', 'priority' => 'medium', 'is_internal' => 1]);

        foreach (['Public task', 'Private task'] as $title) {
            $task = Task::withoutGlobalScopes()->where('title', $title)->firstOrFail();
            $this->assertSame(1, $task->assignees()->withoutGlobalScopes()->count(), 'a solo freelancer is assigned automatically');
            $this->actingAs($sam)->post(route('tasks.complete', $task))->assertRedirect();
            $this->assertSame('approved', $task->fresh()->status);
        }
        $this->assertSame(1, AppNotification::withoutGlobalScopes()->where('user_id', $alice->id)->where('type', 'task')->count());
        $this->actingAs($alice)->post(route('tasks.complete', Task::withoutGlobalScopes()->firstOrFail()))->assertRedirect(route('portal.dashboard'));
    }

    public function test_task_board_and_calendar_views_and_moves(): void
    {
        $sam = $this->solo();
        $abc = $this->makeClient($sam, 'ABC GmbH', 'abc@example.com');
        $seo = $this->makeProject($abc, $sam, 'SEO Campaign');
        $alice = $this->portalUser($sam, $abc, 'Alice');
        $this->actingAs($sam)->post(route('tasks.store', $seo), ['title' => 'Board task', 'priority' => 'high', 'due_at' => now()->addDays(2)->toDateString()]);
        $task = Task::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($sam)->get(route('tasks.index', ['view' => 'board']))->assertOk()->assertSee('Board task')->assertSee('Drag a card');
        $this->actingAs($sam)->get(route('tasks.index', ['view' => 'calendar']))->assertOk()->assertSee('Board task');
        $this->actingAs($sam)->get(route('tasks.index', ['view' => 'calendar', 'month' => 'nonsense']))->assertOk();

        foreach (['progress' => 'in_progress', 'review' => 'under_review', 'todo' => 'assigned', 'done' => 'approved'] as $col => $status) {
            $this->actingAs($sam)->postJson(route('tasks.move', $task), ['column' => $col])->assertOk();
            $this->assertSame($status, $task->fresh()->status);
        }
        $this->assertNotNull($task->fresh()->approved_at);
        $this->actingAs($sam)->postJson(route('tasks.move', $task), ['column' => 'progress'])->assertOk();
        $this->assertNull($task->fresh()->approved_at, 'reopening clears the approval');
        $this->actingAs($sam)->postJson(route('tasks.move', $task), ['column' => 'nope'])->assertStatus(422);
        $this->actingAs($alice)->postJson(route('tasks.move', $task), ['column' => 'done'])->assertForbidden();
    }

    public function test_in_a_team_workspace_board_moves_respect_review_rules(): void
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->freelancer($owner);
        $project = $this->projectFor($owner, [$fiona]);
        $task = $this->taskFor($owner, $project, [$fiona], ['status' => 'assigned']);

        $this->actingAs($fiona)->postJson(route('tasks.move', $task), ['column' => 'progress'])->assertOk();
        $this->assertSame('in_progress', $task->fresh()->status);
        $this->actingAs($fiona)->postJson(route('tasks.move', $task), ['column' => 'review'])->assertStatus(422)->assertJsonPath('ok', false);
        $this->actingAs($fiona)->postJson(route('tasks.move', $task), ['column' => 'done'])->assertStatus(422);
        $this->assertSame('in_progress', $task->fresh()->status);

        $stranger = $this->freelancer($owner, 'Gus');
        $this->actingAs($stranger)->postJson(route('tasks.move', $task), ['column' => 'todo'])->assertForbidden();
    }
}
