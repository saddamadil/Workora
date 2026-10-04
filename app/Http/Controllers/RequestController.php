<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientRequest;
use App\Models\Project;
use App\Services\ClientContext;
use App\Services\FileLibrary;
use App\Services\Notifier;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Work requests: a client asks, the freelancer accepts, declines, discusses or turns it into work. */
class RequestController extends Controller
{
    public function __construct(private Tenancy $tenancy, private ClientContext $ctx) {}

    private function guardStaff(): void
    {
        abort_unless($this->tenancy->isStaff(), 403);
    }

    // ------------------------------------------------------------------ client side

    public function portalIndex(Request $request): View
    {
        $client = $this->ctx->client($request);

        return view('portal.requests', ['requests' => ClientRequest::query()->where('client_id', $client->id)->latest()->get()]);
    }

    public function portalCreate(Request $request): View
    {
        $client = $this->ctx->client($request);

        return view('portal.request-form', ['projects' => Project::query()->where('client_id', $client->id)->orderBy('name')->get(['id', 'name'])]);
    }

    public function portalStore(Request $request, FileLibrary $library, Notifier $notifier): RedirectResponse
    {
        $client = $this->ctx->client($request);
        $maxKb = config('workora.max_upload_mb') * 1024;
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'project_id' => ['nullable', 'uuid'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'preferred_deadline' => ['nullable', 'date', 'after_or_equal:today'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', "max:{$maxKb}"],
        ]);
        if (! empty($data['project_id'])) {
            Project::query()->where('client_id', $client->id)->findOrFail($data['project_id']);
        }

        $req = ClientRequest::create(['client_id' => $client->id, 'requested_by' => $request->user()->id, 'status' => 'new'] + collect($data)->except('files')->all());
        foreach ($request->file('files', []) as $upload) {
            if (! $library->isBlocked($upload->getClientOriginalName())) {
                $library->storeUpload($upload, $request->user(), 'Client Files', ['client_id' => $client->id, 'project_id' => $req->project_id, 'visible_to_client' => true, 'attachable_type' => ClientRequest::class, 'attachable_id' => $req->id]);
            }
        }
        AuditLog::record('request.created', $req, ['project_id' => $req->project_id, 'client_id' => $client->id]);
        $notifier->send($this->ctx->staffToNotify(), 'request', $client->name.' sent a work request', $req->title, route('requests.show', $req), $request->user());

        return redirect()->route('portal.requests.show', $req)->with('status', 'Request sent. You will be notified when there is an answer.');
    }

    public function portalShow(Request $request, ClientRequest $clientRequest): View
    {
        $client = $this->ctx->client($request);
        abort_unless($clientRequest->client_id === $client->id, 404);

        return view('portal.request', ['req' => $clientRequest->load('files', 'project:id,name')]);
    }

    // ------------------------------------------------------------------ freelancer side

    public function index(Request $request): View
    {
        $this->guardStaff();
        $status = (string) $request->query('status');

        return view('requests.index', [
            'requests' => ClientRequest::query()->with('client:id,name', 'project:id,name')
                ->when(isset(ClientRequest::STATUSES[$status]), fn ($q) => $q->where('status', $status))->latest()->get(),
            'status' => $status,
            'counts' => ClientRequest::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function show(ClientRequest $clientRequest): View
    {
        $this->guardStaff();

        return view('requests.show', [
            'req' => $clientRequest->load('client', 'project', 'files', 'requestedBy:id,name'),
            'projects' => Project::query()->where('client_id', $clientRequest->client_id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** accept, decline, discuss, in_progress, completed. */
    public function respond(Request $request, ClientRequest $clientRequest, Notifier $notifier): RedirectResponse
    {
        $this->guardStaff();
        $data = $request->validate([
            'status' => ['required', Rule::in(['discussing', 'accepted', 'in_progress', 'completed', 'declined'])],
            'response_note' => [Rule::requiredIf($request->input('status') === 'declined'), 'nullable', 'string', 'max:2000'],
        ]);

        $clientRequest->update(['status' => $data['status'], 'response_note' => $data['response_note'] ?: $clientRequest->response_note]);
        $this->tell($clientRequest, $notifier, $request, 'Your request "'.$clientRequest->title.'" is now: '.ClientRequest::STATUSES[$data['status']]);

        return back()->with('status', 'Updated. The client has been told.');
    }

    public function toTask(Request $request, ClientRequest $clientRequest, Notifier $notifier): RedirectResponse
    {
        $this->guardStaff();
        $this->authorize('create', \App\Models\Task::class);
        $data = $request->validate(['project_id' => ['required', 'uuid']]);
        $project = Project::query()->where('client_id', $clientRequest->client_id)->findOrFail($data['project_id']);

        $task = $project->tasks()->create([
            'title' => $clientRequest->title, 'description' => $clientRequest->description, 'status' => 'backlog',
            'priority' => $clientRequest->priority, 'due_at' => $clientRequest->preferred_deadline, 'created_by' => $request->user()->id, 'currency' => $project->currency,
        ]);
        $task->syncAssignees([$request->user()->id]);
        $clientRequest->update(['status' => 'accepted', 'task_id' => $task->id, 'project_id' => $project->id]);
        AuditLog::record('task.created', $task, ['project_id' => $project->id, 'client_id' => $project->client_id]);
        $this->tell($clientRequest, $notifier, $request, 'Your request "'.$clientRequest->title.'" was accepted and added to '.$project->name);

        return redirect()->route('tasks.show', $task)->with('status', 'Request converted to a task.');
    }

    public function toProject(Request $request, ClientRequest $clientRequest, Notifier $notifier): RedirectResponse
    {
        $this->guardStaff();
        $this->authorize('create', Project::class);
        $client = Client::query()->findOrFail($clientRequest->client_id);

        $project = Project::create([
            'client_id' => $client->id, 'name' => $clientRequest->title, 'slug' => Str::slug($clientRequest->title).'-'.Str::lower(Str::random(5)),
            'description' => $clientRequest->description, 'status' => 'planning', 'currency' => $client->default_currency ?: $this->tenancy->organization()->base_currency,
            'deadline' => $clientRequest->preferred_deadline, 'created_by' => $request->user()->id, 'priority' => $clientRequest->priority,
        ]);
        \App\Models\ProjectMember::create(['project_id' => $project->id, 'user_id' => $request->user()->id, 'role_in_project' => 'owner', 'can_view_budget' => true]);
        $clientRequest->update(['status' => 'accepted', 'converted_project_id' => $project->id, 'project_id' => $project->id]);
        AuditLog::record('project.created', $project, ['project_id' => $project->id, 'client_id' => $client->id]);
        $this->tell($clientRequest, $notifier, $request, 'Your request "'.$clientRequest->title.'" became a new project');

        return redirect()->route('projects.show', $project)->with('status', 'Request converted to a project.');
    }

    private function tell(ClientRequest $req, Notifier $notifier, Request $request, string $title): void
    {
        $client = Client::query()->findOrFail($req->client_id);
        $notifier->send($this->ctx->clientUsers($client), 'request', $title, $req->response_note, route('portal.requests.show', $req), $request->user());
        AuditLog::record('request.'.$req->status, $req, ['project_id' => $req->project_id, 'client_id' => $req->client_id]);
    }
}
