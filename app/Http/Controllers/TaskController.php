<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\File;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\TaskComment;
use App\Models\TaskRevision;
use App\Models\TaskSubmission;
use App\Services\FileLibrary;
use App\Support\Money;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $search = trim($request->string('q')->toString());
        $scope = $request->string('scope')->toString(); // open (default), review, done, all

        $tasks = Task::query()->visibleTo($user)
            ->with('project:id,name,slug', 'assignees:id,name')
            ->when($request->boolean('mine'), fn ($q) => $q->whereHas('assignees', fn ($a) => $a->where('users.id', $user->id)))
            ->when($request->filled('project'), fn ($q) => $q->where('project_id', $request->input('project')))
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($scope === 'review', fn ($q) => $q->whereIn('status', ['submitted', 'under_review']))
            ->when($scope === 'done', fn ($q) => $q->where('status', 'approved'))
            ->when($scope === '' || $scope === 'open', fn ($q) => $q->whereNotIn('status', ['approved', 'cancelled']))
            ->orderByRaw('due_at is null')->orderBy('due_at')->latest()
            ->paginate(30)->withQueryString();

        return view('tasks.index', [
            'tasks' => $tasks,
            'scope' => $scope ?: 'open',
            'search' => $search,
            'mine' => $request->boolean('mine'),
            'reviewCount' => Task::query()->visibleTo($user)->whereIn('status', ['submitted', 'under_review'])->count(),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('create', Task::class);
        $this->authorize('view', $project);

        $data = $this->validated($request, $project);
        $task = $project->tasks()->create($data['fields'] + ['created_by' => $request->user()->id, 'currency' => $project->currency, 'status' => 'backlog']);
        $task->syncAssignees($data['assignees']);

        return redirect()->route('tasks.show', $task)->with('status', 'Task created.');
    }

    public function show(Request $request, Task $task): View
    {
        $this->authorize('view', $task);
        $user = $request->user();

        $task->load([
            'project', 'assignees:id,name,email', 'checklistItems',
            'comments' => fn ($q) => $q->with('user:id,name')->oldest(),
            'submissions' => fn ($q) => $q->with('submittedBy:id,name', 'reviewedBy:id,name'),
            'revisions' => fn ($q) => $q->orderBy('number'),
            'files',
        ]);

        $submissionFiles = File::query()->where('attachable_type', TaskSubmission::class)
            ->whereIn('attachable_id', $task->submissions->pluck('id'))->get()->groupBy('attachable_id');

        $entries = $task->timeEntries()->with('user:id,name')
            ->when(app(Tenancy::class)->isFreelancer(), fn ($q) => $q->where('user_id', $user->id))
            ->latest('entry_date')->limit(15)->get();

        return view('tasks.show', [
            'task' => $task,
            'submissionFiles' => $submissionFiles,
            'entries' => $entries,
            'canEdit' => $user->can('update', $task),
            'canWork' => $user->can('work', $task),
            'canReview' => $user->can('review', $task),
            'candidates' => $user->can('update', $task)
                ? ProjectMember::query()->with('user:id,name')->where('project_id', $task->project_id)->get()->pluck('user')
                : collect(),
        ]);
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $data = $this->validated($request, $task->project);
        $task->update($data['fields']);
        $task->syncAssignees($data['assignees']);

        return back()->with('status', 'Task saved.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);
        $project = $task->project;
        $task->delete();

        return redirect()->route('projects.show', $project)->with('status', 'Task deleted.');
    }

    /** On a solo workspace there is nobody to review the work, so the freelancer marks it done. */
    public function complete(Request $request, Task $task): RedirectResponse
    {
        abort_unless(app(Tenancy::class)->isSolo(), 403);
        $this->authorize('update', $task);
        abort_if(in_array($task->status, ['approved', 'cancelled'], true), 422, 'This task is already closed.');

        $task->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        \App\Models\AuditLog::record('task.completed', $task, ['project_id' => $task->project_id]);

        return back()->with('status', 'Task completed.');
    }

    /** The assignee begins work. */
    public function start(Task $task): RedirectResponse
    {
        $this->authorize('work', $task);
        abort_unless(in_array($task->status, ['assigned', 'revision_required'], true), 422, 'This task cannot be started right now.');

        $task->update(['status' => 'in_progress']);

        return back()->with('status', 'Task started.');
    }

    /** The assignee hands the work in for review, with a note and any files. */
    public function submit(Request $request, Task $task, FileLibrary $library): RedirectResponse
    {
        $this->authorize('work', $task);
        abort_unless($task->canBeSubmitted(), 422, 'This task cannot be submitted right now.');

        $maxKb = config('workora.max_upload_mb') * 1024;
        $data = $request->validate([
            'note' => ['required', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:20'],
            'files.*' => ['file', "max:{$maxKb}"],
        ]);

        $submission = TaskSubmission::create([
            'task_id' => $task->id,
            'submitted_by' => $request->user()->id,
            'note' => $data['note'],
            'status' => 'pending',
        ]);

        foreach ($request->file('files', []) as $upload) {
            if ($library->isBlocked($upload->getClientOriginalName())) {
                continue;
            }
            $library->storeUpload($upload, $request->user(), 'Deliverables', [
                'project_id' => $task->project_id,
                'attachable_type' => TaskSubmission::class,
                'attachable_id' => $submission->id,
            ]);
        }

        // Anything the reviewer asked for last time is now answered.
        $task->openRevisions()->update(['status' => 'resolved', 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
        $task->update(['status' => 'submitted']);

        return back()->with('status', 'Submitted for review.');
    }

    /** A reviewer approves the work or sends it back with a list of changes. */
    public function review(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('review', $task);
        abort_unless(in_array($task->status, ['submitted', 'under_review'], true), 422, 'Nothing is waiting for review.');

        $data = $request->validate([
            'decision' => ['required', 'in:approve,revise'],
            'note' => ['nullable', 'string', 'max:2000'],
            'issues' => ['required_if:decision,revise', 'nullable', 'string', 'max:5000'],
        ]);

        $submission = $task->submissions()->first();
        $approving = $data['decision'] === 'approve';

        $submission?->update([
            'status' => $approving ? 'approved' : 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $data['note'] ?? null,
        ]);

        if ($approving) {
            $task->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            AuditLog::record('task.approved', $task);
            $message = 'Work approved.';
        } else {
            // One revision item per line, so the freelancer gets a checklist rather than a paragraph.
            foreach (preg_split('/\R+/', trim($data['issues'])) as $line) {
                if (trim($line) !== '') {
                    TaskRevision::create([
                        'task_id' => $task->id,
                        'task_submission_id' => $submission?->id,
                        'raised_by' => $request->user()->id,
                        'issue' => mb_substr(trim($line), 0, 250),
                        'status' => 'open',
                    ]);
                }
            }
            $task->update(['status' => 'revision_required']);
            AuditLog::record('task.revision_requested', $task);
            $message = 'Sent back with changes requested.';
        }

        return back()->with('status', $message);
    }

    public function cancel(Task $task): RedirectResponse
    {
        $this->authorize('update', $task);
        abort_if($task->status === 'approved', 422, 'Approved work cannot be cancelled.');

        $task->update(['status' => 'cancelled']);

        return back()->with('status', 'Task cancelled.');
    }

    public function reopen(Task $task): RedirectResponse
    {
        $this->authorize('update', $task);
        abort_unless($task->status === 'cancelled', 422);

        $task->update(['status' => $task->assignees()->exists() ? 'assigned' : 'backlog']);

        return back()->with('status', 'Task reopened.');
    }

    public function comment(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('view', $task);
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);

        TaskComment::create(['task_id' => $task->id, 'user_id' => $request->user()->id, 'body' => $data['body']]);

        return back();
    }

    public function attach(Request $request, Task $task, FileLibrary $library): RedirectResponse
    {
        $this->authorize('view', $task);
        abort_unless($request->user()->can('update', $task) || $request->user()->can('work', $task), 403);

        $maxKb = config('workora.max_upload_mb') * 1024;
        $request->validate(['files' => ['required', 'array', 'max:20'], 'files.*' => ['file', "max:{$maxKb}"]]);

        foreach ($request->file('files') as $upload) {
            if (! $library->isBlocked($upload->getClientOriginalName())) {
                $library->storeUpload($upload, $request->user(), 'Task files', [
                    'project_id' => $task->project_id,
                    'attachable_type' => Task::class,
                    'attachable_id' => $task->id,
                ]);
            }
        }

        return back()->with('status', 'Files attached.');
    }

    public function addChecklistItem(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeChecklist($request, $task);
        $data = $request->validate(['title' => ['required', 'string', 'max:200']]);

        TaskChecklistItem::create(['task_id' => $task->id, 'title' => $data['title'], 'position' => $task->checklistItems()->count()]);

        return back();
    }

    public function toggleChecklistItem(Request $request, Task $task, TaskChecklistItem $item): RedirectResponse
    {
        $this->authorizeChecklist($request, $task);
        abort_unless($item->task_id === $task->id, 404);

        $item->update(['is_done' => ! $item->is_done]);

        return back();
    }

    public function deleteChecklistItem(Request $request, Task $task, TaskChecklistItem $item): RedirectResponse
    {
        $this->authorizeChecklist($request, $task);
        abort_unless($item->task_id === $task->id, 404);
        $item->delete();

        return back();
    }

    private function authorizeChecklist(Request $request, Task $task): void
    {
        $this->authorize('view', $task);
        abort_unless($request->user()->can('update', $task) || $request->user()->can('work', $task), 403);
    }

    /** @return array{fields: array, assignees: array<int, string>} */
    private function validated(Request $request, Project $project): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'due_at' => ['nullable', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'assignee_ids' => ['nullable', 'array', 'max:20'],
            'assignee_ids.*' => ['uuid'],
            'is_internal' => ['sometimes', 'boolean'],
            'milestone_id' => ['nullable', 'uuid'],
        ]);
        if (! empty($data['milestone_id'])) {
            \App\Models\ProjectMilestone::query()->where('project_id', $project->id)->findOrFail($data['milestone_id']);
        }

        // Only people on the project can be given its tasks.
        $assignees = array_values(array_unique($data['assignee_ids'] ?? []));
        // On their own, a freelancer is the only person who can do the work.
        if (! $assignees && app(Tenancy::class)->isSolo()) {
            $assignees = [$request->user()->id];
        }
        $members = ProjectMember::where('project_id', $project->id)->whereIn('user_id', $assignees)->pluck('user_id')->all();
        abort_unless(count($members) === count($assignees), 422, 'Add people to the project before assigning them tasks.');

        return [
            'assignees' => $assignees,
            'fields' => [
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'priority' => $data['priority'],
                'due_at' => $data['due_at'] ?? null,
                'estimated_hours' => $data['estimated_hours'] ?? null,
                'budget_minor' => Money::toMinor($data['budget'] ?? null),
                'is_internal' => $request->boolean('is_internal'),
                'milestone_id' => $data['milestone_id'] ?? null,
            ],
        ];
    }
}
