<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\WorkRequest;
use App\Models\WorkRequestMessage;
use App\Support\Money;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Work a freelancer proposes rather than waits to be given: they describe it and
 * name a price, the company counters or agrees in a thread, and approval turns
 * it into a task.
 */
class WorkRequestController extends Controller
{
    public function __construct(private Tenancy $tenancy) {}

    public function index(Request $request): View
    {
        $mine = $this->tenancy->isFreelancer();
        abort_unless($mine || $this->tenancy->role()?->canApproveWork(), 403);

        $requests = WorkRequest::query()->with('requestedBy:id,name', 'project:id,name')
            ->when($mine, fn ($q) => $q->where('requested_by', $request->user()->id))
            ->orderByRaw("case when status in ('submitted','negotiating','under_review') then 0 else 1 end")->latest()->paginate(25);

        return view('work-requests.index', ['requests' => $requests, 'mine' => $mine]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->tenancy->isFreelancer(), 403);

        return view('work-requests.create', ['projects' => Project::query()->visibleTo($request->user())->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->tenancy->isFreelancer(), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:5000'],
            'project_id' => ['nullable', 'uuid'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'proposed_deadline' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        if (! empty($data['project_id'])) {
            Project::query()->visibleTo($request->user())->findOrFail($data['project_id']);
        }

        $wr = WorkRequest::create([
            'requested_by' => $request->user()->id,
            'project_id' => $data['project_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'],
            'estimated_hours' => $data['estimated_hours'] ?? null,
            'proposed_amount_minor' => Money::toMinor($data['amount']),
            'currency' => $this->tenancy->organization()->base_currency,
            'proposed_deadline' => $data['proposed_deadline'] ?? null,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return redirect()->route('work-requests.show', $wr)->with('status', 'Proposal sent to '.$this->tenancy->organization()->name.'.');
    }

    public function show(Request $request, WorkRequest $workRequest): View
    {
        $this->authorizeSee($request, $workRequest);

        return view('work-requests.show', [
            'wr' => $workRequest->load('requestedBy:id,name', 'project:id,name,slug', 'messages.user:id,name', 'convertedTask'),
            'currentAmount' => $workRequest->currentAmountMinor(),
            'canDecide' => ($this->tenancy->role()?->canApproveWork() ?? false) && $workRequest->isOpen(),
            'isOwner' => $workRequest->requested_by === $request->user()->id,
            'projects' => Project::query()->whereIn('status', ['planning', 'active'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function message(Request $request, WorkRequest $workRequest): RedirectResponse
    {
        $this->authorizeSee($request, $workRequest);
        abort_unless($workRequest->isOpen(), 422, 'This proposal is closed.');

        $data = $request->validate([
            'body' => ['required_without:amount', 'nullable', 'string', 'max:3000'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'hours' => ['nullable', 'numeric', 'min:0'],
            'deadline' => ['nullable', 'date'],
        ]);

        WorkRequestMessage::create([
            'work_request_id' => $workRequest->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'] ?? null,
            'proposed_amount_minor' => Money::toMinor($data['amount'] ?? null),
            'proposed_hours' => $data['hours'] ?? null,
            'proposed_deadline' => $data['deadline'] ?? null,
        ]);
        $workRequest->update(['status' => 'negotiating']);

        return back();
    }

    /** Agree to the work: it becomes a task assigned to the freelancer at the agreed price. */
    public function approve(Request $request, WorkRequest $workRequest): RedirectResponse
    {
        abort_unless($this->tenancy->role()?->canApproveWork(), 403);
        abort_unless($workRequest->isOpen(), 422);

        $data = $request->validate(['project_id' => ['required', 'uuid'], 'note' => ['nullable', 'string', 'max:1000']]);
        $project = Project::query()->findOrFail($data['project_id']);

        $lastMessage = $workRequest->messages()->reorder()->whereNotNull('proposed_hours')->latest()->first();
        $deadline = $workRequest->messages()->reorder()->whereNotNull('proposed_deadline')->latest()->first()?->proposed_deadline ?? $workRequest->proposed_deadline;

        // The freelancer has to be on the project to work on it.
        ProjectMember::firstOrCreate(['project_id' => $project->id, 'user_id' => $workRequest->requested_by], ['role_in_project' => 'freelancer']);

        $task = $project->tasks()->create([
            'title' => $workRequest->title,
            'description' => $workRequest->description,
            'status' => 'backlog',
            'priority' => 'medium',
            'due_at' => $deadline,
            'estimated_hours' => $lastMessage?->proposed_hours ?? $workRequest->estimated_hours,
            'budget_minor' => $workRequest->currentAmountMinor(),
            'currency' => $workRequest->currency,
            'created_by' => $request->user()->id,
        ]);
        $task->syncAssignees([$workRequest->requested_by]);

        $workRequest->update([
            'status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(),
            'response_note' => $data['note'] ?? null, 'converted_task_id' => $task->id, 'project_id' => $project->id,
        ]);
        AuditLog::record('work_request.approved', $workRequest);

        return redirect()->route('tasks.show', $task)->with('status', 'Approved. The proposal is now a task.');
    }

    public function reject(Request $request, WorkRequest $workRequest): RedirectResponse
    {
        abort_unless($this->tenancy->role()?->canApproveWork(), 403);
        abort_unless($workRequest->isOpen(), 422);

        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        $workRequest->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'response_note' => $data['note']]);

        return back()->with('status', 'Proposal declined.');
    }

    public function withdraw(Request $request, WorkRequest $workRequest): RedirectResponse
    {
        abort_unless($workRequest->requested_by === $request->user()->id && $workRequest->isOpen(), 403);
        $workRequest->update(['status' => 'withdrawn']);

        return back()->with('status', 'Proposal withdrawn.');
    }

    private function authorizeSee(Request $request, WorkRequest $workRequest): void
    {
        if ($this->tenancy->isFreelancer()) {
            abort_unless($workRequest->requested_by === $request->user()->id, 404);

            return;
        }

        abort_unless($this->tenancy->role()?->canApproveWork(), 403);
    }
}
