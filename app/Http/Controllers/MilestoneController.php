<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Services\ClientContext;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MilestoneController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date'],
        ]);

        $m = $project->milestones()->create($data + ['position' => (int) $project->milestones()->max('position') + 1]);
        AuditLog::record('milestone.created', $m, ['project_id' => $project->id, 'client_id' => $project->client_id]);

        return back()->with('status', 'Milestone added.');
    }

    public function update(Request $request, ProjectMilestone $milestone, Notifier $notifier, ClientContext $ctx): RedirectResponse
    {
        $project = Project::query()->findOrFail($milestone->project_id);
        $this->authorize('update', $project);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(array_keys(ProjectMilestone::STATUSES))],
        ]);

        $was = $milestone->status;
        $milestone->update($data + ($data['status'] ?? null ? ['completed_at' => $data['status'] === 'completed' ? now() : null] : []));

        if (($data['status'] ?? null) === 'completed' && $was !== 'completed') {
            AuditLog::record('milestone.completed', $milestone, ['project_id' => $project->id, 'client_id' => $project->client_id]);
            if ($project->client_id) {
                $notifier->send($ctx->clientUsers(Client::query()->findOrFail($project->client_id)), 'project', $project->name.': '.$milestone->title.' is complete', null, route('portal.project', $project->slug), $request->user());
            }
        }

        return back()->with('status', 'Milestone saved.');
    }

    public function destroy(ProjectMilestone $milestone): RedirectResponse
    {
        $this->authorize('update', Project::query()->findOrFail($milestone->project_id));
        $milestone->delete();

        return back()->with('status', 'Milestone removed.');
    }
}
