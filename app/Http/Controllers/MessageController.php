<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientRequest;
use App\Models\Message;
use App\Models\Project;
use App\Models\Task;
use App\Services\ClientContext;
use App\Services\Conversations;
use App\Services\FileLibrary;
use App\Services\Notifier;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Messaging for both sides. Staff pick a client; a client login is fixed to their own. */
class MessageController extends Controller
{
    public function __construct(private Tenancy $tenancy, private ClientContext $ctx, private Conversations $conversations) {}

    private function guard(): void
    {
        abort_unless($this->ctx->isPortal() || $this->tenancy->isStaff(), 403);
    }

    private function projectFor(Client $client, ?string $id): ?Project
    {
        return $id ? Project::query()->where('client_id', $client->id)->findOrFail($id) : null;
    }

    public function index(Request $request): View
    {
        $this->guard();
        $me = $request->user();
        $portal = $this->ctx->isPortal();

        $clients = $portal ? collect() : Client::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'logo_path']);
        $client = $portal ? $this->ctx->client($request) : ($request->filled('client') ? $this->ctx->client($request) : $clients->first());

        $data = ['portal' => $portal, 'clients' => $clients, 'client' => $client, 'threads' => collect(), 'messages' => collect(), 'project' => null, 'search' => '', 'unreadByClient' => collect()];

        if (! $portal) {
            $data['unreadByClient'] = $clients->mapWithKeys(fn ($c) => [$c->id => $this->conversations->threads($c, $me)->sum('unread')]);
        }

        if ($client) {
            $project = $this->projectFor($client, $request->query('project'));
            $search = trim((string) $request->query('q'));
            $messages = $this->conversations->messages($client, $project?->id, $search);
            $this->conversations->markRead($me, $client, $project?->id);

            $data = array_merge($data, [
                'threads' => $this->conversations->threads($client, $me),
                'messages' => $messages,
                'project' => $project,
                'search' => $search,
                'others' => $this->ctx->audienceFor($me, $client),
                'projects' => Project::query()->where('client_id', $client->id)->orderBy('name')->get(['id', 'name']),
            ]);
        }

        return view('messages.index', $data);
    }

    /** Polled every few seconds by the open thread. */
    public function poll(Request $request): JsonResponse
    {
        $this->guard();
        $client = $this->ctx->client($request);
        $project = $this->projectFor($client, $request->query('project'));
        $messages = $this->conversations->messages($client, $project?->id, trim((string) $request->query('q')));
        $this->conversations->markRead($request->user(), $client, $project?->id);
        $others = $this->ctx->audienceFor($request->user(), $client);

        return response()->json([
            'last' => $messages->last()?->id,
            'count' => $messages->count(),
            'html' => view('messages._list', ['messages' => $messages, 'portal' => $this->ctx->isPortal()])->render(),
            'typing' => $this->conversations->whoIsTyping($client, $project?->id, $request->user(), $others),
            'online' => $others->contains(fn ($u) => Conversations::isOnline($u)),
        ]);
    }

    public function store(Request $request, FileLibrary $library, Notifier $notifier): RedirectResponse|JsonResponse
    {
        $this->guard();
        $me = $request->user();
        $client = $this->ctx->client($request);
        $maxKb = config('workora.max_upload_mb') * 1024;

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'project_id' => ['nullable', 'uuid'],
            'parent_id' => ['nullable', 'uuid'],
            'invoice_id' => ['nullable', 'uuid'],
            'is_important' => ['sometimes', 'boolean'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', "max:{$maxKb}"],
        ]);

        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '' && ! $request->hasFile('files')) {
            return back()->withErrors(['body' => 'Write a message or attach a file.']);
        }

        $project = $this->projectFor($client, $data['project_id'] ?? null);
        if (! empty($data['parent_id'])) {
            Message::query()->where('client_id', $client->id)->findOrFail($data['parent_id']);
        }
        if (! empty($data['invoice_id'])) {
            \App\Models\Invoice::query()->where('client_id', $client->id)->findOrFail($data['invoice_id']);
        }

        $message = Message::create([
            'client_id' => $client->id, 'project_id' => $project?->id, 'user_id' => $me->id,
            'parent_id' => $data['parent_id'] ?? null, 'invoice_id' => $data['invoice_id'] ?? null,
            'body' => $body === '' ? '(attachment)' : $body, 'is_important' => $request->boolean('is_important'),
        ]);

        foreach ($request->file('files', []) as $upload) {
            if ($library->isBlocked($upload->getClientOriginalName())) {
                continue;
            }
            $library->storeUpload($upload, $me, 'Messages', [
                'project_id' => $project?->id, 'client_id' => $client->id, 'visible_to_client' => true,
                'attachable_type' => Message::class, 'attachable_id' => $message->id,
            ]);
        }

        $this->conversations->markRead($me, $client, $project?->id);
        AuditLog::record('message.sent', $message, ['project_id' => $project?->id, 'client_id' => $client->id]);

        $where = $project ? $client->name.' / '.$project->name : $client->name;
        $notifier->send($this->ctx->audienceFor($me, $client), 'message', $me->name.' sent a message', $where.': '.str($body)->limit(120), $this->threadUrl($client, $project?->id), $me);

        if ($request->expectsJson()) {
            return response()->json(['id' => $message->id], 201);
        }

        return redirect($this->threadUrl($client, $project?->id));
    }

    public function important(Request $request, Message $message): RedirectResponse
    {
        $this->guard();
        $this->owned($request, $message);
        $message->update(['is_important' => ! $message->is_important]);

        return back();
    }

    public function typing(Request $request): JsonResponse
    {
        $this->guard();
        $client = $this->ctx->client($request);
        $this->conversations->typing($request->user(), $client, $this->projectFor($client, $request->input('project_id'))?->id);

        return response()->json(['ok' => true]);
    }

    /** Staff: turn a message into a task on the message's project. */
    public function toTask(Request $request, Message $message): RedirectResponse
    {
        abort_unless($this->tenancy->isStaff() || $this->tenancy->issuesOwnInvoices(), 403);
        $this->authorize('create', Task::class);
        $project = Project::query()->findOrFail($message->project_id ?? $request->input('project_id'));
        abort_unless($project->client_id === $message->client_id, 404);

        $data = $request->validate(['title' => ['required', 'string', 'max:200']]);
        $task = $project->tasks()->create([
            'title' => $data['title'], 'description' => $message->body, 'status' => 'backlog', 'priority' => 'medium',
            'created_by' => $request->user()->id, 'currency' => $project->currency,
        ]);
        $task->syncAssignees([$request->user()->id]);
        AuditLog::record('task.created', $task, ['project_id' => $project->id, 'client_id' => $message->client_id]);

        return redirect()->route('tasks.show', $task)->with('status', 'Task created from the message.');
    }

    /** Client: turn a message into a work request for the freelancer. */
    public function toRequest(Request $request, Message $message, Notifier $notifier): RedirectResponse
    {
        abort_unless($this->ctx->isPortal(), 403);
        $client = $this->ctx->client($request);
        abort_unless($message->client_id === $client->id, 404);

        $data = $request->validate(['title' => ['required', 'string', 'max:200']]);
        $req = ClientRequest::create([
            'client_id' => $client->id, 'project_id' => $message->project_id, 'requested_by' => $request->user()->id,
            'title' => $data['title'], 'description' => $message->body, 'priority' => 'medium', 'status' => 'new',
        ]);
        AuditLog::record('request.created', $req, ['project_id' => $req->project_id, 'client_id' => $client->id]);
        $notifier->send($this->ctx->staffToNotify(), 'request', $client->name.' sent a work request', $req->title, route('requests.show', $req), $request->user());

        return redirect()->route('portal.requests.show', $req)->with('status', 'Request sent.');
    }

    private function owned(Request $request, Message $message): void
    {
        $client = $this->ctx->client($request, required: false);
        $clientId = $this->ctx->isPortal() ? $client->id : $message->client_id;
        abort_unless($message->client_id === $clientId, 404);
    }

    private function threadUrl(Client $client, ?string $projectId): string
    {
        $q = array_filter(['client' => $this->ctx->isPortal() ? null : $client->id, 'project' => $projectId]);

        return route(($this->ctx->isPortal() ? 'portal.' : '').'messages.index', $q);
    }
}
