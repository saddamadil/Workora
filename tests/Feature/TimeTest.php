<?php

namespace Tests\Feature;

use App\Models\TimeEntry;
use App\Models\Timesheet;

class TimeTest extends WorkoraTestCase
{
    private function setup3(): array
    {
        $owner = $this->userWithWorkspace('Olive');
        $fiona = $this->freelancer($owner, 'Fiona', 40000);
        $project = $this->projectFor($owner, [$fiona]);
        $task = $this->taskFor($owner, $project, [$fiona]);
        $this->activeContract($owner, $fiona, ['hourly_rate_minor' => 60000]);

        return [$owner, $fiona, $project, $task];
    }

    public function test_manual_time_uses_the_contract_rate_and_updates_the_task(): void
    {
        [$owner, $fiona, $project, $task] = $this->setup3();

        $this->actingAs($fiona)->post(route('time.store'), [
            'project_id' => $project->id, 'task_id' => $task->id, 'entry_date' => now()->toDateString(), 'duration' => '1:30', 'description' => 'Wireframes',
        ])->assertRedirect();

        $entry = TimeEntry::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(90, $entry->minutes);
        $this->assertSame(60000, $entry->rate_minor, 'contract rate wins over the default rate');
        $this->assertSame(90000, $entry->amountMinor());
        $this->assertSame('1.50', (string) $task->fresh()->actual_hours);

        $this->actingAs($fiona)->get(route('time.index'))->assertOk()->assertSee('Wireframes')->assertSee('1h 30m');
    }

    public function test_decimal_hours_and_bad_input(): void
    {
        [, $fiona, $project] = $this->setup3();

        $this->actingAs($fiona)->post(route('time.store'), ['project_id' => $project->id, 'entry_date' => now()->toDateString(), 'duration' => '2.5'])->assertRedirect();
        $this->assertSame(150, TimeEntry::withoutGlobalScopes()->firstOrFail()->minutes);

        $this->actingAs($fiona)->post(route('time.store'), ['project_id' => $project->id, 'entry_date' => now()->toDateString(), 'duration' => 'abc'])->assertSessionHasErrors('duration');
        $this->actingAs($fiona)->post(route('time.store'), ['project_id' => $project->id, 'entry_date' => now()->addDay()->toDateString(), 'duration' => '1'])->assertSessionHasErrors('entry_date');
    }

    public function test_cannot_log_time_on_a_project_or_task_you_are_not_part_of(): void
    {
        [$owner, $fiona] = $this->setup3();
        $other = $this->projectFor($owner);
        $otherTask = $this->taskFor($owner, $other);

        $this->actingAs($fiona)->post(route('time.store'), ['project_id' => $other->id, 'entry_date' => now()->toDateString(), 'duration' => '1'])->assertNotFound();
        $this->actingAs($fiona)->post(route('time.store'), ['project_id' => $other->id, 'task_id' => $otherTask->id, 'entry_date' => now()->toDateString(), 'duration' => '1'])->assertNotFound();
    }

    public function test_timer_starts_stops_and_only_one_runs_at_a_time(): void
    {
        [, $fiona, $project, $task] = $this->setup3();

        $this->actingAs($fiona)->post(route('time.start'), ['project_id' => $project->id, 'task_id' => $task->id])->assertRedirect();
        $this->assertSame('in_progress', $task->fresh()->status, 'starting the clock starts the task');
        $this->actingAs($fiona)->post(route('time.start'), ['project_id' => $project->id])->assertSessionHas('error');

        $this->travel(47)->minutes();
        $this->actingAs($fiona)->post(route('time.stop'))->assertSessionHas('status');

        $entry = TimeEntry::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(47, $entry->minutes);
        $this->assertSame('timer', $entry->source);
        $this->actingAs($fiona)->post(route('time.stop'))->assertSessionHas('error');
    }

    public function test_week_submission_review_and_locking(): void
    {
        [$owner, $fiona, $project] = $this->setup3();

        $this->actingAs($fiona)->post(route('time.store'), ['project_id' => $project->id, 'entry_date' => now()->toDateString(), 'duration' => '4'])->assertRedirect();
        $this->actingAs($fiona)->post(route('time.store'), ['project_id' => $project->id, 'entry_date' => now()->toDateString(), 'duration' => '2', 'is_billable' => '0'])->assertRedirect();

        $this->actingAs($fiona)->post(route('time.submit-week'), ['week' => now()->toDateString()])->assertSessionHas('status');
        $ts = Timesheet::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('submitted', $ts->status);
        $this->assertSame(240, $ts->total_minutes, 'total_minutes counts billable time only');
        $this->assertSame(240000, $ts->total_amount_minor, '4 billable hours at 600.00');
        $this->assertSame(360, (int) TimeEntry::withoutGlobalScopes()->where('timesheet_id', $ts->id)->sum('minutes'), 'all logged time is still on the sheet');

        // A submitted week is frozen for the freelancer.
        $this->actingAs($fiona)->post(route('time.store'), ['project_id' => $project->id, 'entry_date' => now()->toDateString(), 'duration' => '1'])->assertSessionHas('error');
        $entry = TimeEntry::withoutGlobalScopes()->firstOrFail();
        $this->actingAs($fiona)->delete(route('time.destroy', $entry))->assertSessionHas('error');
        $this->actingAs($fiona)->post(route('time.submit-week'), ['week' => now()->toDateString()])->assertSessionHas('error');

        // Only the company reviews it.
        $this->actingAs($fiona)->post(route('timesheets.approve', $ts))->assertForbidden();
        $this->actingAs($fiona)->get(route('timesheets.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('timesheets.index'))->assertOk()->assertSee('Fiona');
        $this->actingAs($owner)->get(route('timesheets.show', $ts))->assertOk()->assertSee('6h 00m')->assertSee('4h 00m billable');
        $this->actingAs($owner)->post(route('timesheets.reject', $ts), [])->assertSessionHasErrors('note');
        $this->actingAs($owner)->post(route('timesheets.reject', $ts), ['note' => 'Missing Tuesday'])->assertRedirect();

        // Sent back: editable again, with the reason visible.
        $this->actingAs($fiona)->get(route('time.index'))->assertSee('Missing Tuesday');
        $this->actingAs($fiona)->post(route('time.store'), ['project_id' => $project->id, 'entry_date' => now()->toDateString(), 'duration' => '1'])->assertSessionHas('status');
        $this->actingAs($fiona)->post(route('time.submit-week'), ['week' => now()->toDateString()])->assertSessionHas('status');

        $this->actingAs($owner)->post(route('timesheets.approve', $ts->fresh()))->assertRedirect();
        $this->assertSame('approved', $ts->fresh()->status);
        $this->assertSame(300, $ts->fresh()->total_minutes, '4h + the 1h added after it was sent back');
    }

    public function test_company_cannot_see_another_companys_timesheets(): void
    {
        [, $fiona, $project] = $this->setup3();
        $bob = $this->userWithWorkspace('Bob');

        $this->actingAs($fiona)->post(route('time.store'), ['project_id' => $project->id, 'entry_date' => now()->toDateString(), 'duration' => '3']);
        $this->actingAs($fiona)->post(route('time.submit-week'), ['week' => now()->toDateString()]);
        $ts = Timesheet::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($bob)->get(route('timesheets.show', $ts))->assertNotFound();
        $this->actingAs($bob)->get(route('timesheets.index'))->assertDontSee('Fiona');
    }
}
