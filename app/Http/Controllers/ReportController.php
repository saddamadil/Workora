<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Plain numbers about the business: money in, money owed, time spent. Amounts are never added across currencies. */
class ReportController extends Controller
{
    public const RANGES = ['month' => 'This month', 'quarter' => 'Last 3 months', 'year' => 'This year', 'all' => 'All time'];

    public function __construct(private Tenancy $tenancy) {}

    public function index(Request $request): View
    {
        abort_unless($this->tenancy->issuesOwnInvoices() || $this->tenancy->role()?->seesMoney(), 403);
        $range = array_key_exists($request->query('range'), self::RANGES) ? $request->query('range') : 'year';
        $from = match ($range) {
            'month' => now()->startOfMonth(), 'quarter' => now()->subMonths(2)->startOfMonth(), 'year' => now()->startOfYear(), default => Carbon::create(2000, 1, 1),
        };

        $sentStatuses = ['submitted', 'under_review', 'approved', 'partially_paid', 'paid'];
        $invoices = Invoice::query()->with('client:id,name', 'project:id,name')->whereIn('status', $sentStatuses)->whereDate('issue_date', '>=', $from->toDateString())->get();
        $open = $invoices->whereIn('status', ['submitted', 'under_review', 'approved', 'partially_paid']);
        $payments = Payment::query()->with('invoice:id,client_id,project_id')->where('status', 'paid')->whereDate('paid_at', '>=', $from->toDateString())->get();
        $byCurrency = fn ($rows, callable $fn) => $rows->groupBy('currency')->map(fn ($g) => (int) $g->sum($fn))->filter()->all();

        $entries = TimeEntry::query()->with('project:id,name,client_id,currency', 'project.client:id,name')->where('minutes', '>', 0)->whereDate('entry_date', '>=', $from->toDateString())->get();

        // Twelve months of money received, per currency, for the bars.
        $months = collect(range(11, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());
        $received = Payment::query()->where('status', 'paid')->whereDate('paid_at', '>=', $months->first()->toDateString())->get();
        $monthly = $months->map(function ($m) use ($received) {
            $rows = $received->filter(fn ($p) => $p->paid_at->isSameMonth($m));

            return ['label' => $m->format('M'), 'by' => $rows->groupBy('currency')->map(fn ($g) => (int) $g->sum('amount_minor'))->all()];
        });

        $clientRows = $invoices->groupBy('client_id')->map(function ($rows) use ($payments) {
            $client = $rows->first()->client;
            $cur = $rows->first()->currency;

            return ['name' => $client?->name ?? 'No client', 'currency' => $cur, 'invoiced' => (int) $rows->where('currency', $cur)->sum('total_minor'),
                'received' => (int) $payments->filter(fn ($p) => $rows->pluck('id')->contains($p->invoice_id))->where('currency', $cur)->sum('amount_minor'),
                'outstanding' => (int) $rows->whereIn('status', ['submitted', 'under_review', 'approved', 'partially_paid'])->where('currency', $cur)->sum(fn ($i) => $i->total_minor - $i->amount_paid_minor)];
        })->sortByDesc('invoiced')->values();

        $projectRows = $entries->groupBy('project_id')->map(function ($rows, $pid) use ($invoices) {
            $project = $rows->first()->project;
            $minutes = (int) $rows->sum('minutes');
            $billedIn = $invoices->where('project_id', $pid);
            $invoiced = (int) $billedIn->where('currency', $project->currency)->sum('total_minor');
            $hours = $minutes / 60;

            return ['name' => $project->name, 'client' => $project->client?->name, 'currency' => $project->currency, 'minutes' => $minutes,
                'billable' => (int) $rows->where('is_billable', true)->sum('minutes'), 'invoiced' => $invoiced, 'effective' => $hours > 0 ? (int) round($invoiced / $hours) : null];
        })->sortByDesc('minutes')->values();

        return view('reports.index', [
            'range' => $range, 'ranges' => self::RANGES,
            'invoiced' => $byCurrency($invoices, fn ($i) => $i->total_minor),
            'receivedTotals' => $byCurrency($payments, fn ($p) => $p->amount_minor),
            'outstanding' => $byCurrency($open, fn ($i) => $i->total_minor - $i->amount_paid_minor),
            'overdue' => $byCurrency($open->filter(fn ($i) => $i->displayStatus() === 'overdue'), fn ($i) => $i->total_minor - $i->amount_paid_minor),
            'overdueCount' => $open->filter(fn ($i) => $i->displayStatus() === 'overdue')->count(),
            'paidCount' => $invoices->where('status', 'paid')->count(), 'openCount' => $open->count(),
            'currencies' => $invoices->pluck('currency')->unique()->sort()->values()->map(fn ($c) => [
                'currency' => $c, 'invoiced' => (int) $invoices->where('currency', $c)->sum('total_minor'), 'received' => (int) $payments->where('currency', $c)->sum('amount_minor'),
                'outstanding' => (int) $open->where('currency', $c)->sum(fn ($i) => $i->total_minor - $i->amount_paid_minor)]),
            'monthly' => $monthly, 'monthlyCurrency' => $received->pluck('currency')->countBy()->sortDesc()->keys()->first() ?? $this->tenancy->organization()->base_currency,
            'clients' => $clientRows, 'topClients' => $clientRows->sortByDesc('received')->take(5)->values(),
            'projects' => $projectRows,
            'hours' => ['total' => (int) $entries->sum('minutes'), 'billable' => (int) $entries->where('is_billable', true)->sum('minutes'), 'non' => (int) $entries->where('is_billable', false)->sum('minutes')],
            'hoursByClient' => $entries->groupBy(fn ($e) => $e->project->client?->name ?? 'No client')->map(fn ($g) => (int) $g->sum('minutes'))->sortDesc(),
        ]);
    }
}
