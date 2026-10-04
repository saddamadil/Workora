<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use App\Services\Rates;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

class TimeController extends Controller
{
    public function __construct(private Rates $rates, private Tenancy $tenancy) {}

    public function index(Request $request): View
    {
        $this->authorize('track-time');
        $user = $request->user();

        $weekStart = $this->weekStart($request->query('week'));
        $weekEnd = $weekStart->copy()->endOfWeek();

        // Finished entries only: a running timer has no minutes yet and shows in its own card.
        $entries = TimeEntry::query()->where('user_id', $user->id)->where('minutes', '>', 0)
            ->whereDate('entry_date', '>=', $weekStart->toDateString())->whereDate('entry_date', '<=', $weekEnd->toDateString())
            ->with('project:id,name,slug', 'task:id,title')->orderBy('entry_date')->orderBy('created_at')->get();

        $timesheet = Timesheet::query()->where('user_id', $user->id)->whereDate('period_start', $weekStart)->first();
        $running = TimeEntry::query()->where('user_id', $user->id)->whereNotNull('started_at')->whereNull('ended_at')->with('project:id,name', 'task:id,title')->first();

        $projects = Project::query()->visibleTo($user)->whereIn('status', ['planning', 'active'])->orderBy('name')->get(['id', 'name']);
        $tasks = Task::query()->visibleTo($user)->whereNotIn('status', ['approved', 'cancelled'])->orderBy('title')->get(['id', 'project_id', 'title']);

        $billable = $entries->where('is_billable', true);

        return view('time.index', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'days' => collect(range(0, 6))->map(fn ($i) => $weekStart->copy()->addDays($i)),
            'entries' => $entries,
            'timesheet' => $timesheet,
            'running' => $running,
            'projects' => $projects,
            'tasks' => $tasks,
            'totalMinutes' => (int) $entries->sum('minutes'),
            'estimateMinor' => (int) $billable->sum(fn (TimeEntry $e) => $e->amountMinor()),
            'editable' => ! $timesheet || $timesheet->isEditable(),
            'month' => $this->monthSummary($user),
            'solo' => $this->tenancy->isSolo(),
            'showMoney' => $this->tenancy->isFreelancer() || Gate::allows('see-money'),
        ]);
    }

    /** This calendar month: hours, billable hours and what they are worth, per project currency. */
    private function monthSummary($user): array
    {
        $entries = TimeEntry::query()->with('project:id,name,currency')->where('user_id', $user->id)->where('minutes', '>', 0)
            ->whereDate('entry_date', '>=', now()->startOfMonth()->toDateString())->whereDate('entry_date', '<=', now()->endOfMonth()->toDateString())->get();

        return [
            'total' => (int) $entries->sum('minutes'),
            'billable' => (int) $entries->where('is_billable', true)->sum('minutes'),
            'value' => $entries->where('is_billable', true)->groupBy(fn ($e) => $e->project->currency)->map(fn ($g) => (int) $g->sum(fn ($e) => $e->amountMinor()))->all(),
        ];
    }

    public function start(Request $request): RedirectResponse
    {
        $this->authorize('track-time');
        $user = $request->user();

        $data = $request->validate([
            'task_id' => ['nullable', 'uuid'],
            'project_id' => ['nullable', 'uuid'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if (TimeEntry::where('user_id', $user->id)->whereNotNull('started_at')->whereNull('ended_at')->exists()) {
            return back()->with('error', 'A timer is already running. Stop it first.');
        }

        [$project, $task] = $this->resolveTarget($user, $data['project_id'] ?? null, $data['task_id'] ?? null);

        if ($blocked = $this->weekLocked($user, now())) {
            return back()->with('error', $blocked);
        }

        TimeEntry::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'task_id' => $task?->id,
            'entry_date' => now()->toDateString(),
            'started_at' => now(),
            'minutes' => 0,
            'description' => $data['description'] ?? null,
            'is_billable' => true,
            'rate_minor' => $this->rates->hourlyFor($user, $project),
            'client_id' => $project->client_id,
            'source' => 'timer',
        ]);

        // Starting the clock on a to-do task means the work has begun.
        if ($task && $task->status === 'assigned' && $task->assignees()->where('users.id', $user->id)->exists()) {
            $task->update(['status' => 'in_progress']);
        }

        return back()->with('status', 'Timer started.');
    }

    public function stop(Request $request): RedirectResponse
    {
        $entry = TimeEntry::where('user_id', $request->user()->id)->whereNotNull('started_at')->whereNull('ended_at')->first();

        if (! $entry) {
            return back()->with('error', 'No timer is running.');
        }

        $end = now();
        $entry->update(['ended_at' => $end, 'minutes' => max(1, (int) round($entry->started_at->diffInSeconds($end) / 60))]);
        $entry->task?->refreshActualHours();

        return back()->with('status', 'Logged '.hours($entry->minutes).'.');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('track-time');
        $user = $request->user();

        $data = $request->validate([
            'project_id' => ['required', 'uuid'],
            'task_id' => ['nullable', 'uuid'],
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'duration' => ['required', 'string', 'max:10'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_billable' => ['nullable', 'boolean'],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ]);

        $minutes = $this->parseDuration($data['duration']);
        if (! $minutes || $minutes > 24 * 60) {
            return back()->withInput()->withErrors(['duration' => 'Enter a time like 1:30 or 1.5 (up to 24 hours).']);
        }

        [$project, $task] = $this->resolveTarget($user, $data['project_id'], $data['task_id'] ?? null);

        if ($blocked = $this->weekLocked($user, Carbon::parse($data['entry_date']))) {
            return back()->withInput()->with('error', $blocked);
        }

        TimeEntry::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'task_id' => $task?->id,
            'entry_date' => $data['entry_date'],
            'minutes' => $minutes,
            'description' => $data['description'] ?? null,
            'is_billable' => $request->boolean('is_billable', true),
            'rate_minor' => isset($data['rate']) && $data['rate'] !== null ? \App\Support\Money::toMinor($data['rate']) : $this->rates->hourlyFor($user, $project),
            'client_id' => $project->client_id,
            'source' => 'manual',
        ]);
        $task?->refreshActualHours();

        return back()->with('status', 'Logged '.hours($minutes).'.');
    }

    public function destroy(Request $request, TimeEntry $entry): RedirectResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 403);

        if ($entry->timesheet && ! $entry->timesheet->isEditable()) {
            return back()->with('error', 'This time is on a submitted timesheet and cannot be removed.');
        }

        try {
            $entry->delete();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $entry->task?->refreshActualHours();

        return back()->with('status', 'Entry removed.');
    }

    /** Send the week to the company for approval. */
    public function submitWeek(Request $request): RedirectResponse
    {
        $this->authorize('track-time');
        $user = $request->user();
        $weekStart = $this->weekStart($request->input('week'));
        $weekEnd = $weekStart->copy()->endOfWeek();

        if (TimeEntry::where('user_id', $user->id)->whereNotNull('started_at')->whereNull('ended_at')->whereDate('entry_date', '>=', $weekStart->toDateString())->whereDate('entry_date', '<=', $weekEnd->toDateString())->exists()) {
            return back()->with('error', 'Stop your running timer before submitting the week.');
        }

        // whereDate, not equality: the stored value is a date cast and may carry a time part.
        $timesheet = Timesheet::where('user_id', $user->id)->whereDate('period_start', $weekStart)->first()
            ?? new Timesheet(['user_id' => $user->id, 'period_start' => $weekStart, 'period_end' => $weekEnd]);

        if ($timesheet->exists && ! $timesheet->isEditable()) {
            return back()->with('error', 'This week was already submitted.');
        }

        $entries = TimeEntry::where('user_id', $user->id)->whereDate('entry_date', '>=', $weekStart->toDateString())->whereDate('entry_date', '<=', $weekEnd->toDateString())->where('minutes', '>', 0)->get();

        if ($entries->isEmpty()) {
            return back()->with('error', 'There is no time to submit for this week.');
        }

        $timesheet->fill([
            'status' => 'submitted',
            'submitted_at' => now(),
            'currency' => $this->tenancy->organization()->base_currency,
            'contract_id' => $this->rates->activeHourlyContract($user)?->id,
            'review_note' => null,
        ])->save();

        TimeEntry::whereKey($entries->pluck('id'))->update(['timesheet_id' => $timesheet->id]);
        $timesheet->recalculate();

        return back()->with('status', 'Week submitted for approval.');
    }

    /** @return array{0: Project, 1: ?Task} */
    private function resolveTarget($user, ?string $projectId, ?string $taskId): array
    {
        $task = $taskId ? Task::query()->visibleTo($user)->with('project')->findOrFail($taskId) : null;
        abort_if($task && $task->isClosed(), 422, 'That task is already closed.');

        $project = $task?->project ?? Project::query()->visibleTo($user)->findOrFail($projectId);
        abort_if($taskId && $projectId && $task->project_id !== $projectId, 422, 'That task is not on this project.');

        return [$project, $task];
    }

    /** Time cannot be added to a week that is already with the company or approved. */
    private function weekLocked($user, Carbon $date): ?string
    {
        $ts = Timesheet::where('user_id', $user->id)->whereDate('period_start', $date->copy()->startOfWeek())->first();

        return $ts && ! $ts->isEditable()
            ? 'The week of '.$ts->period_start->format('d M').' is already submitted. Ask your manager to reject it if something needs adding.'
            : null;
    }

    private function weekStart(?string $week): Carbon
    {
        try {
            return ($week ? Carbon::parse($week) : now())->startOfWeek();
        } catch (\Throwable) {
            return now()->startOfWeek();
        }
    }

    /** "1:30", "1.5" or "90" (minutes only when it has no colon or dot and exceeds 24) to minutes. */
    private function parseDuration(string $input): ?int
    {
        $input = trim($input);

        if (preg_match('/^(\d{1,2}):([0-5]\d)$/', $input, $m)) {
            return (int) $m[1] * 60 + (int) $m[2];
        }

        return is_numeric($input) ? (int) round((float) $input * 60) : null;
    }
}
