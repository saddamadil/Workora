<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\File;
use App\Models\Invoice;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Models\Task;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The client portal. Every query here is pinned to the one client record this login belongs to,
 * and only the fields a client may see are selected: no budgets, rates, private notes, other
 * clients or the freelancer's revenue. The middleware keeps client logins out of every other route.
 */
class PortalController extends Controller
{
    private const OPEN = ['submitted', 'under_review', 'approved', 'partially_paid'];

    /** Audit entries a client may see on a project timeline. Nothing internal. */
    private const CLIENT_VISIBLE_ACTIONS = ['project.created', 'file.shared', 'milestone.completed', 'invoice.sent', 'deliverable.submitted', 'deliverable.approved', 'deliverable.changes_requested', 'request.created', 'request.accepted', 'request.completed'];

    public function __construct(private Tenancy $tenancy) {}

    private function client(): Client
    {
        $id = $this->tenancy->clientId();
        abort_if($id === null, 403);

        return Client::query()->findOrFail($id);
    }

    private function freelancerName(): string
    {
        $owner = OrganizationMember::query()->with('user:id,name')->where('role', 'owner')->where('status', 'active')->oldest()->first();

        return $owner?->user?->name ?? $this->tenancy->organization()->name;
    }

    public function dashboard(): View
    {
        $client = $this->client();
        $projects = Project::query()->where('client_id', $client->id)->get(['id', 'name', 'slug', 'status', 'deadline']);
        $projectIds = $projects->pluck('id');

        $taskQuery = fn () => Task::query()->whereIn('project_id', $projectIds)->whereNotIn('status', ['cancelled']);
        $invoices = Invoice::query()->where('client_id', $client->id)->whereIn('status', array_merge(self::OPEN, ['paid']))->get();
        $due = $invoices->whereIn('status', self::OPEN);

        return view('portal.dashboard', [
            'client' => $client,
            'freelancer' => $this->freelancerName(),
            'active' => $projects->where('status', 'active')->count(),
            'completed' => $projects->where('status', 'completed')->count(),
            'pendingTasks' => $taskQuery()->where('status', '!=', 'approved')->count(),
            'invoicesDue' => $due->count(),
            'dueTotals' => $due->groupBy('currency')->map(fn ($g) => (int) $g->sum(fn ($i) => $i->total_minor - $i->amount_paid_minor))->all(),
            'projects' => $this->withProgress($projects->whereIn('status', ['planning', 'active', 'on_hold', 'review'])->values()),
            'upcoming' => [
                'deadlines' => $projects->filter(fn ($p) => $p->deadline && $p->deadline->between(now()->startOfDay(), now()->addDays(30)))->sortBy('deadline')->take(4)->values(),
                'invoices' => $due->filter(fn ($i) => $i->due_date->lte(now()->addDays(30)))->sortBy('due_date')->take(4)->values(),
            ],
            'recentInvoices' => $invoices->sortByDesc('issue_date')->take(4)->values(),
        ]);
    }

    public function projects(): View
    {
        $client = $this->client();
        $projects = Project::query()->where('client_id', $client->id)->latest()->get(['id', 'name', 'slug', 'status', 'deadline']);

        return view('portal.projects', ['projects' => $this->withProgress($projects)]);
    }

    public function tasks(): View
    {
        $client = $this->client();
        $projects = Project::query()->where('client_id', $client->id)->get(['id', 'name', 'slug']);
        $tasks = Task::query()->whereIn('project_id', $projects->pluck('id'))->where('is_internal', false)->where('status', '!=', 'cancelled')
            ->orderByRaw('due_at is null')->orderBy('due_at')->get(['id', 'project_id', 'title', 'status', 'due_at', 'priority']);

        return view('portal.tasks', ['projects' => $projects->keyBy('id'), 'tasks' => $tasks->groupBy('project_id')]);
    }

    public function project(Request $request, Project $project): View
    {
        $client = $this->client();
        abort_unless($project->client_id === $client->id, 404);
        $tab = in_array($request->query('tab'), ['overview', 'tasks', 'files', 'milestones', 'activity'], true) ? $request->query('tab') : 'overview';

        $tasks = Task::query()->where('project_id', $project->id)->where('is_internal', false)->where('status', '!=', 'cancelled')->orderByRaw('due_at is null')->orderBy('due_at')->get(['id', 'title', 'status', 'due_at']);
        $done = $tasks->where('status', 'approved')->count();

        $hours = $project->share_hours ? (int) \App\Models\TimeEntry::query()->where('project_id', $project->id)->where('is_billable', true)->sum('minutes') : null;

        return view('portal.project', [
            'project' => $project,
            'tab' => $tab,
            'tasks' => $tasks,
            'progress' => $tasks->count() ? (int) round($done / $tasks->count() * 100) : 0,
            'done' => $done,
            'hours' => $hours,
            'milestones' => $project->milestones()->get(),
            'deliverables' => $project->deliverables()->with('reviews.user:id,name', 'files')->get(),
            'files' => File::query()->where('client_id', $client->id)->where('project_id', $project->id)->where('visible_to_client', true)->latest()->get(),
            'activity' => $tab === 'activity' ? AuditLog::query()->with('user:id,name')->where('project_id', $project->id)->whereIn('action', self::CLIENT_VISIBLE_ACTIONS)->latest('created_at')->limit(60)->get() : collect(),
        ]);
    }

    public function invoices(): View
    {
        $client = $this->client();
        $invoices = Invoice::query()->where('client_id', $client->id)->whereIn('status', array_merge(self::OPEN, ['paid', 'void', 'refunded']))->latest('issue_date')->get();
        $open = $invoices->whereIn('status', self::OPEN);

        return view('portal.invoices', [
            'invoices' => $invoices,
            'due' => $open->groupBy('currency')->map(fn ($g) => (int) $g->sum(fn ($i) => $i->total_minor - $i->amount_paid_minor))->all(),
            'paid' => $invoices->groupBy('currency')->map(fn ($g) => (int) $g->sum('amount_paid_minor'))->filter()->all(),
            'overdue' => $open->filter(fn ($i) => $i->displayStatus() === 'overdue')->count(),
            'nextDue' => $open->sortBy('due_date')->first(),
            'history' => \App\Models\Payment::query()->with('invoice:id,number')->whereIn('invoice_id', $invoices->pluck('id'))->where('status', 'paid')->latest('paid_at')->limit(15)->get(),
        ]);
    }

    /** What is due, the next due date and everything paid so far. */
    public function payments(): View
    {
        $client = $this->client();
        $invoices = Invoice::query()->where('client_id', $client->id)->whereIn('status', array_merge(self::OPEN, ['paid']))->get();
        $open = $invoices->whereIn('status', self::OPEN);

        return view('portal.payments', [
            'due' => $open->groupBy('currency')->map(fn ($g) => (int) $g->sum(fn ($i) => $i->total_minor - $i->amount_paid_minor))->all(),
            'nextDue' => $open->sortBy('due_date')->first(),
            'open' => $open->sortBy('due_date')->values(),
            'history' => \App\Models\Payment::query()->with('invoice:id,number')->whereIn('invoice_id', $invoices->pluck('id'))->where('status', 'paid')->latest('paid_at')->get(),
        ]);
    }

    public function invoice(Request $request, Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        // Record the first time this person opens it, so the freelancer can see it was read.
        $seen = AuditLog::query()->where('action', 'invoice.viewed')->where('auditable_id', $invoice->id)->where('user_id', $request->user()->id)->exists();
        if (! $seen) {
            AuditLog::record('invoice.viewed', $invoice);
        }

        $invoice->load('payments', 'payoutMethod');
        $reports = \App\Models\PaymentReport::query()->where('invoice_id', $invoice->id)->latest()->get();

        return view('portal.invoice', [
            'invoice' => $invoice,
            'payout' => app(\App\Services\InvoiceDocument::class)->payment($invoice),
            'reports' => $reports,
        ]);
    }

    /** The client's own company details. They fill in invoices addressed to this client. */
    public function company(): View
    {
        return view('portal.company', ['client' => $this->client()]);
    }

    public function updateCompany(Request $request, \App\Services\ImageStore $images): RedirectResponse
    {
        $client = $this->client();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:200'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'default_currency' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Support\Money::CURRENCIES))],
            'tax_ids' => ['nullable', 'array'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);

        [$taxIds, $errors] = \App\Support\TaxFields::clean($data['country_code'] ?? null, $data['tax_ids'] ?? []);
        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages(collect($errors)->mapWithKeys(fn ($m, $k) => ["tax_ids.$k" => $m])->all());
        }
        $data['tax_ids'] = $taxIds ?: null;
        $data['country_code'] = isset($data['country_code']) ? strtoupper($data['country_code']) : null;

        try {
            if ($request->hasFile('logo')) {
                $images->delete($client->logo_path);
                $data['logo_path'] = $images->store($request->file('logo'), 'branding/clients', 600);
            }
        } catch (\RuntimeException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(['logo' => $e->getMessage()]);
        }
        unset($data['logo']);

        // Only these fields: status, type and the freelancer's private notes are not the client's to change.
        $client->update($data);
        AuditLog::record('client.profile_updated', $client);

        return back()->with('status', 'Company details saved. They will be used on your next invoices.');
    }

    public function profile(Request $request): View
    {
        return view('portal.profile', ['user' => $request->user(), 'client' => $this->client()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $request->user()->update($data);

        return back()->with('status', 'Saved.');
    }

    /** Projects with a 0 to 100 progress figure from approved tasks. */
    private function withProgress($projects)
    {
        $counts = Task::query()->whereIn('project_id', $projects->pluck('id'))->where('status', '!=', 'cancelled')
            ->selectRaw("project_id, count(*) as total, sum(case when status = 'approved' then 1 else 0 end) as done")->groupBy('project_id')->get()->keyBy('project_id');

        return $projects->map(function ($p) use ($counts) {
            $c = $counts[$p->id] ?? null;
            $p->progress = $c && $c->total ? (int) round($c->done / $c->total * 100) : 0;

            return $p;
        });
    }
}
