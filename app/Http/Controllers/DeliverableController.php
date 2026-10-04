<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\DeliverableReview;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Services\ClientContext;
use App\Services\FileLibrary;
use App\Services\Notifier;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Deliverables: the freelancer hands work in for review, the client approves it or asks for changes.
 * Every decision is kept as a review round, and the project status follows along.
 */
class DeliverableController extends Controller
{
    public function __construct(private Tenancy $tenancy, private ClientContext $ctx, private Notifier $notifier) {}

    // ------------------------------------------------------------------ freelancer

    public function store(Request $request, Project $project, FileLibrary $library): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($project->client_id, 422, 'Give the project a client before sending work for review.');
        $maxKb = config('workora.max_upload_mb') * 1024;

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:3000'],
            'milestone_id' => ['nullable', 'uuid'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', "max:{$maxKb}"],
        ]);
        if (! empty($data['milestone_id'])) {
            ProjectMilestone::query()->where('project_id', $project->id)->findOrFail($data['milestone_id']);
        }

        $d = Deliverable::create([
            'project_id' => $project->id, 'title' => $data['title'], 'description' => $data['description'] ?? null,
            'milestone_id' => $data['milestone_id'] ?? null, 'status' => 'in_review',
            'submitted_by' => $request->user()->id, 'submitted_at' => now(),
        ]);
        $this->attach($request, $library, $d, $project);
        $this->moveProject($project, 'review');

        AuditLog::record('deliverable.submitted', $d, ['project_id' => $project->id, 'client_id' => $project->client_id]);
        $this->notifier->send($this->ctx->clientUsers(Client::query()->findOrFail($project->client_id)), 'approval', 'Ready for your review: '.$d->title, $project->name, route('portal.project', [$project->slug, 'tab' => 'overview']), $request->user());

        return back()->with('status', 'Sent to the client for review.');
    }

    /** After changes were requested: send the same deliverable back for another look. */
    public function resubmit(Request $request, Deliverable $deliverable, FileLibrary $library): RedirectResponse
    {
        $project = Project::query()->findOrFail($deliverable->project_id);
        $this->authorize('update', $project);
        abort_unless($deliverable->status === 'changes_requested', 422, 'This deliverable is not waiting for changes.');

        $deliverable->update(['status' => 'in_review', 'submitted_at' => now(), 'decided_at' => null]);
        $this->attach($request, $library, $deliverable, $project);
        $this->moveProject($project, 'review');
        AuditLog::record('deliverable.submitted', $deliverable, ['project_id' => $project->id, 'client_id' => $project->client_id]);
        $this->notifier->send($this->ctx->clientUsers(Client::query()->findOrFail($project->client_id)), 'approval', 'Updated and ready for review: '.$deliverable->title, $project->name, route('portal.project', [$project->slug]), $request->user());

        return back()->with('status', 'Sent back for review.');
    }

    // ------------------------------------------------------------------ client

    public function approve(Request $request, Deliverable $deliverable): RedirectResponse
    {
        [$project] = $this->guardClient($request, $deliverable);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);

        $this->record($request, $deliverable, 'approved', $data['comment'] ?? null);
        $deliverable->update(['status' => 'approved', 'decided_at' => now()]);

        if ($deliverable->milestone_id) {
            ProjectMilestone::query()->whereKey($deliverable->milestone_id)->update(['status' => 'completed', 'completed_at' => now()]);
        }
        if (! $project->deliverables()->where('status', 'in_review')->exists()) {
            $this->moveProject($project, 'active');
        }

        AuditLog::record('deliverable.approved', $deliverable, ['project_id' => $project->id, 'client_id' => $project->client_id]);
        $this->notifier->send($this->ctx->staffToNotify(), 'approval', 'Approved: '.$deliverable->title, $project->name, route('projects.show', [$project, 'tab' => 'milestones']), $request->user());

        return back()->with('status', 'Approved. Thank you.');
    }

    public function requestChanges(Request $request, Deliverable $deliverable, FileLibrary $library): RedirectResponse
    {
        [$project, $client] = $this->guardClient($request, $deliverable);
        $maxKb = config('workora.max_upload_mb') * 1024;
        $data = $request->validate(['comment' => ['required', 'string', 'max:3000'], 'files' => ['nullable', 'array', 'max:10'], 'files.*' => ['file', "max:{$maxKb}"]]);

        $review = $this->record($request, $deliverable, 'changes_requested', $data['comment']);
        foreach ($request->file('files', []) as $upload) {
            if (! $library->isBlocked($upload->getClientOriginalName())) {
                $library->storeUpload($upload, $request->user(), 'Client Files', ['project_id' => $project->id, 'client_id' => $client->id, 'visible_to_client' => true, 'attachable_type' => DeliverableReview::class, 'attachable_id' => $review->id]);
            }
        }
        $deliverable->update(['status' => 'changes_requested', 'decided_at' => now()]);
        $this->moveProject($project, 'revision_requested');

        AuditLog::record('deliverable.changes_requested', $deliverable, ['project_id' => $project->id, 'client_id' => $project->client_id]);
        $this->notifier->send($this->ctx->staffToNotify(), 'revision', 'Changes requested: '.$deliverable->title, $client->name.': '.str($data['comment'])->limit(140), route('projects.show', [$project, 'tab' => 'milestones']), $request->user());

        return back()->with('status', 'Your feedback was sent.');
    }

    // ------------------------------------------------------------------ helpers

    private function guardClient(Request $request, Deliverable $deliverable): array
    {
        abort_unless($this->ctx->isPortal(), 403);
        // Only the main contact decides on work; colleagues can read, comment and message.
        abort_unless($this->tenancy->isClientOwner(), 403, 'Only the main contact of your company can approve work.');
        $client = $this->ctx->client($request);
        $project = Project::query()->where('client_id', $client->id)->findOrFail($deliverable->project_id);
        abort_unless($deliverable->status === 'in_review', 422, 'This deliverable is not waiting for review.');

        return [$project, $client];
    }

    private function record(Request $request, Deliverable $deliverable, string $decision, ?string $comment): DeliverableReview
    {
        return DeliverableReview::create([
            'deliverable_id' => $deliverable->id, 'user_id' => $request->user()->id, 'decision' => $decision, 'comment' => $comment,
            'round' => $deliverable->reviews()->count() + 1, 'created_at' => now(),
        ]);
    }

    /** Project status follows the review, but never overrides a project that is finished or paused by hand. */
    private function moveProject(Project $project, string $status): void
    {
        if (in_array($project->status, ['completed', 'cancelled', 'on_hold'], true)) {
            return;
        }
        $project->update(['status' => $status]);
    }

    private function attach(Request $request, FileLibrary $library, Deliverable $d, Project $project): void
    {
        foreach ($request->file('files', []) as $upload) {
            if (! $library->isBlocked($upload->getClientOriginalName())) {
                $library->storeUpload($upload, $request->user(), 'Deliverables', ['project_id' => $project->id, 'client_id' => $project->client_id, 'visible_to_client' => true, 'attachable_type' => Deliverable::class, 'attachable_id' => $d->id]);
            }
        }
    }
}
