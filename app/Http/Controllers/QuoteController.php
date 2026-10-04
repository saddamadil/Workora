<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Proposal;
use App\Services\ClientContext;
use App\Services\Notifier;
use App\Services\PlanLimits;
use App\Support\Money;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Quotes for clients. When the client accepts one, a project is created from it. */
class QuoteController extends Controller
{
    public function __construct(private Tenancy $tenancy) {}

    private function guard(): void
    {
        abort_unless($this->tenancy->issuesOwnInvoices() || $this->tenancy->role()?->canApprovePayment() || $this->tenancy->role()?->seesMoney(), 403);
    }

    public function index(): View
    {
        $this->guard();

        return view('quotes.index', ['quotes' => Proposal::query()->with('client:id,name')->latest()->get()]);
    }

    public function create(Request $request): View
    {
        $this->guard();

        return view('quotes.form', ['quote' => new Proposal(['currency' => 'INR', 'items' => [['description' => '', 'quantity' => 1, 'unit_rate_minor' => 0]], 'client_id' => $request->query('client')]), 'clients' => Client::query()->orderBy('name')->get(['id', 'name']), 'currencies' => array_keys(Money::CURRENCIES)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard();
        $data = $this->validated($request);
        $quote = Proposal::create($data + ['user_id' => $request->user()->id, 'number' => $this->nextNumber(), 'status' => 'draft']);
        AuditLog::record('quote.created', $quote, ['client_id' => $quote->client_id]);

        return redirect()->route('quotes.show', $quote)->with('status', 'Quote saved as a draft.');
    }

    public function show(Proposal $quote): View
    {
        $this->guard();

        return view('quotes.show', ['quote' => $quote->load('client:id,name,email', 'author:id,name', 'project:id,name,slug')]);
    }

    public function edit(Proposal $quote): View
    {
        $this->guard();
        abort_unless($quote->status === 'draft', 422, 'Only drafts can be edited.');

        return view('quotes.form', ['quote' => $quote, 'clients' => Client::query()->orderBy('name')->get(['id', 'name']), 'currencies' => array_keys(Money::CURRENCIES)]);
    }

    public function update(Request $request, Proposal $quote): RedirectResponse
    {
        $this->guard();
        abort_unless($quote->status === 'draft', 422, 'Only drafts can be edited.');
        $quote->update($this->validated($request));

        return redirect()->route('quotes.show', $quote)->with('status', 'Quote updated.');
    }

    public function send(Request $request, Proposal $quote, ClientContext $ctx, Notifier $notifier): RedirectResponse
    {
        $this->guard();
        abort_unless(in_array($quote->status, ['draft', 'sent'], true), 422);
        abort_if($quote->totalMinor() <= 0, 422, 'Add at least one priced line before sending.');
        $quote->update(['status' => 'sent', 'sent_at' => now()]);
        AuditLog::record('quote.sent', $quote, ['client_id' => $quote->client_id]);
        $client = Client::query()->findOrFail($quote->client_id);
        $notifier->send($ctx->clientUsers($client), 'approval', 'New quote: '.$quote->title, money($quote->totalMinor(), $quote->currency).($quote->valid_until ? ' - valid until '.$quote->valid_until->format('d M Y') : ''), route('portal.quotes.show', $quote), $request->user());

        return back()->with('status', 'Quote sent. The client can accept or decline it in their portal.');
    }

    public function destroy(Proposal $quote): RedirectResponse
    {
        $this->guard();
        abort_unless(in_array($quote->status, ['draft', 'declined'], true), 422, 'Only drafts and declined quotes can be deleted.');
        $quote->delete();

        return redirect()->route('quotes.index')->with('status', 'Quote deleted.');
    }

    /** Called by the portal when a client accepts: the project the work will happen in. */
    public static function startProject(Proposal $quote, ?string $createdBy): Project
    {
        app(PlanLimits::class)->ensureRoomFor('projects');
        $base = Str::slug($quote->title) ?: 'project';
        $slug = $base;
        while (Project::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }
        $project = Project::create([
            'name' => $quote->title, 'slug' => $slug, 'client_id' => $quote->client_id, 'description' => $quote->intro, 'status' => 'active', 'currency' => $quote->currency,
            'budget_minor' => $quote->totalMinor(), 'billing_model' => 'fixed', 'priority' => 'medium', 'created_by' => $createdBy ?? $quote->user_id, 'share_hours' => false,
        ]);
        ProjectMember::firstOrCreate(['project_id' => $project->id, 'user_id' => $quote->user_id], ['role_in_project' => 'manager', 'can_view_budget' => true]);
        AuditLog::record('project.created', $project, ['project_id' => $project->id, 'client_id' => $project->client_id, 'from_quote' => $quote->id]);

        return $project;
    }

    private function nextNumber(): string
    {
        $year = now()->format('Y');
        $n = Proposal::query()->where('number', 'like', "Q-{$year}-%")->count() + 1;
        while (Proposal::query()->where('number', sprintf('Q-%s-%03d', $year, $n))->exists()) {
            $n++;
        }

        return sprintf('Q-%s-%03d', $year, $n);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'client_id' => ['required', 'uuid', Rule::exists('clients', 'id')->where('organization_id', $this->tenancy->id())],
            'title' => ['required', 'string', 'max:160'],
            'intro' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'currency' => ['required', Rule::in(array_keys(Money::CURRENCIES))],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_label' => ['nullable', 'string', 'max:30'],
            'items' => ['required', 'array', 'min:1', 'max:60'],
            'items.*.description' => ['required', 'string', 'max:250'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'items.*.unit_rate' => ['required', 'numeric', 'min:0'],
        ]);
        $data['items'] = collect($data['items'])->map(fn ($i) => ['description' => $i['description'], 'quantity' => (float) $i['quantity'], 'unit_rate_minor' => (int) Money::toMinor($i['unit_rate'])])->values()->all();
        $data['tax_rate'] = (float) ($data['tax_rate'] ?? 0);

        return $data;
    }
}
