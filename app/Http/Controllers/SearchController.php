<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\Project;
use App\Models\Task;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** One search box for everything the signed-in person is allowed to see, grouped by kind. */
class SearchController extends Controller
{
    public function __construct(private Tenancy $tenancy) {}

    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json(['groups' => []]);
        }

        $like = '%'.addcslashes($q, '%_\\').'%';
        $user = $request->user();
        $portal = $this->tenancy->isClient();
        $clientId = $this->tenancy->clientId();
        $groups = [];
        $add = function (string $title, $items) use (&$groups) {
            if ($items->isNotEmpty()) {
                $groups[] = ['title' => $title, 'items' => $items->values()->all()];
            }
        };

        if (! $portal && $this->tenancy->isStaff()) {
            $add('Clients', Client::query()->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('contact_name', 'like', $like))->limit(5)->get()
                ->map(fn ($c) => ['title' => $c->name, 'sub' => $c->email, 'url' => route('clients.show', $c)]));
        }

        $projects = Project::query()->where('name', 'like', $like)->when($portal, fn ($p) => $p->where('client_id', $clientId), fn ($p) => $p->visibleTo($user))->limit(5)->get(['id', 'name', 'slug', 'status']);
        $add('Projects', $projects->map(fn ($p) => ['title' => $p->name, 'sub' => Project::STATUS_LABELS[$p->status] ?? $p->status, 'url' => $portal ? route('portal.project', $p->slug) : route('projects.show', $p)]));

        $tasks = Task::query()->with('project:id,name,slug')->where('title', 'like', $like)
            ->when($portal, fn ($t) => $t->where('is_internal', false)->whereHas('project', fn ($p) => $p->where('client_id', $clientId)), fn ($t) => $t->visibleTo($user))->limit(5)->get();
        $add('Tasks', $tasks->map(fn ($t) => ['title' => $t->title, 'sub' => $t->project?->name, 'url' => $portal ? route('portal.project', [$t->project->slug, 'tab' => 'tasks']) : route('tasks.show', $t)]));

        if ($portal || $this->tenancy->isStaff()) {
            $messages = Message::query()->with('user:id,name')->where('body', 'like', $like)->when($portal, fn ($m) => $m->where('client_id', $clientId))->latest()->limit(5)->get();
            $add('Messages', $messages->map(fn ($m) => ['title' => str($m->body)->limit(80)->toString(), 'sub' => $m->user->name.' · '.$m->created_at->format('d M'),
                'url' => route($portal ? 'portal.messages.index' : 'messages.index', array_filter(['client' => $portal ? null : $m->client_id, 'project' => $m->project_id]))]));
        }

        if ($portal || $this->tenancy->issuesOwnInvoices() || $this->tenancy->role()?->seesMoney()) {
            $invoices = Invoice::query()->where('number', 'like', $like)
                ->when($portal, fn ($i) => $i->where('client_id', $clientId)->whereIn('status', ['submitted', 'under_review', 'approved', 'partially_paid', 'paid', 'void', 'refunded']))->limit(5)->get();
            $add('Invoices', $invoices->map(fn ($i) => ['title' => $i->number, 'sub' => money($i->total_minor, $i->currency).' · '.ucfirst($i->displayStatus()), 'url' => $portal ? route('portal.invoice', $i) : route('invoices.show', $i)]));
        }

        $files = File::query()->where('original_name', 'like', $like)
            ->when($portal, fn ($f) => $f->where('client_id', $clientId)->where('visible_to_client', true), fn ($f) => $f->visibleTo($user))->limit(5)->get();
        $add('Files', $files->map(fn ($f) => ['title' => $f->original_name, 'sub' => $f->humanSize(), 'url' => $portal ? route('portal.files.show', $f) : route('files.show', $f)]));

        return response()->json(['groups' => $groups]);
    }
}
