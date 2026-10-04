<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Expense;
use App\Models\Project;
use App\Services\FileLibrary;
use App\Support\Money;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** What the business spends, with receipts. Billable expenses can be added to a client's invoice at cost. */
class ExpenseController extends Controller
{
    public function __construct(private Tenancy $tenancy) {}

    private function guard(): void
    {
        abort_unless($this->tenancy->issuesOwnInvoices() || $this->tenancy->role()?->canApprovePayment() || $this->tenancy->role()?->seesMoney(), 403);
    }

    public function index(Request $request): View
    {
        $this->guard();
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? $request->query('month') : null;
        $category = in_array($request->query('category'), Expense::CATEGORIES, true) ? $request->query('category') : null;

        $expenses = Expense::query()->with('client:id,name', 'project:id,name', 'receipt:id,original_name')
            ->when($month, fn ($q) => $q->whereBetween('spent_on', [$month.'-01', date('Y-m-t', strtotime($month.'-01'))]))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderByDesc('spent_on')->orderByDesc('created_at')->limit(300)->get();

        return view('expenses.index', [
            'expenses' => $expenses, 'month' => $month, 'category' => $category,
            'byCurrency' => $expenses->groupBy('currency')->map(fn ($g) => (int) $g->sum('amount_minor')),
            'byCategory' => $expenses->groupBy('category')->map(fn ($g) => $g->groupBy('currency')->map(fn ($x) => (int) $x->sum('amount_minor'))),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->whereNotNull('client_id')->orderBy('name')->get(['id', 'name', 'client_id']),
            'currencies' => array_keys(Money::CURRENCIES), 'categories' => Expense::CATEGORIES,
        ]);
    }

    public function store(Request $request, FileLibrary $library): RedirectResponse
    {
        $this->guard();
        $maxKb = config('workora.max_upload_mb') * 1024;
        $data = $request->validate([
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', Rule::in(Expense::CATEGORIES)],
            'description' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'currency' => ['required', Rule::in(array_keys(Money::CURRENCIES))],
            'client_id' => ['nullable', 'uuid', Rule::exists('clients', 'id')->where('organization_id', $this->tenancy->id())],
            'project_id' => ['nullable', 'uuid', Rule::exists('projects', 'id')->where('organization_id', $this->tenancy->id())],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', "max:{$maxKb}"],
        ]);
        $clientId = $data['client_id'] ?? null;
        if (! empty($data['project_id'])) {
            $clientId = Project::query()->whereKey($data['project_id'])->value('client_id') ?? $clientId;
        }
        $billable = $request->boolean('is_billable');
        if ($billable && ! $clientId) {
            return back()->withInput()->withErrors(['client_id' => 'Choose the client to bill this expense to.']);
        }

        $receiptId = null;
        if ($request->hasFile('receipt')) {
            $receiptId = $library->storeUpload($request->file('receipt'), $request->user(), 'Receipts', ['project_id' => $data['project_id'] ?? null])->id;
        }

        Expense::create([
            'user_id' => $request->user()->id, 'client_id' => $clientId, 'project_id' => $data['project_id'] ?? null, 'spent_on' => $data['spent_on'], 'category' => $data['category'],
            'description' => $data['description'], 'amount_minor' => (int) Money::toMinor($data['amount']), 'currency' => $data['currency'], 'is_billable' => $billable, 'receipt_file_id' => $receiptId,
        ]);

        return back()->with('status', 'Expense saved.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->guard();
        abort_if($expense->billed_invoice_id !== null, 422, 'This expense is on an invoice. Remove the invoice line first.');
        $expense->delete();

        return back()->with('status', 'Expense deleted.');
    }
}
