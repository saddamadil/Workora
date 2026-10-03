<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\ContractMilestone;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Support\Money;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContractController extends Controller
{
    public function __construct(private Tenancy $tenancy) {}

    public function index(Request $request): View
    {
        $this->authorizeAny();
        $status = $request->query('status');

        $contracts = Contract::query()->with('freelancer:id,name', 'project:id,name')
            ->when($this->tenancy->isFreelancer(), fn ($q) => $q->where('user_id', $request->user()->id)->where('status', '!=', 'draft'))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()->paginate(30)->withQueryString();

        return view('contracts.index', ['contracts' => $contracts, 'status' => $status]);
    }

    public function create(): View
    {
        $this->authorize('manage-contracts');

        return view('contracts.form', $this->formData(new Contract([
            'type' => 'hourly', 'currency' => $this->tenancy->organization()->base_currency,
            'payment_cycle' => 'monthly', 'payment_terms_days' => 15, 'starts_on' => now(),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-contracts');

        $contract = Contract::create($this->validated($request) + [
            'reference' => $this->nextReference(),
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('contracts.show', $contract)->with('status', 'Contract saved as a draft. Review it, then send it to the freelancer.');
    }

    public function show(Request $request, Contract $contract): View
    {
        $this->authorizeView($request, $contract);

        return view('contracts.show', [
            'contract' => $contract->load('freelancer:id,name,email', 'project:id,name,slug', 'milestones', 'invoices'),
            'isFreelancer' => $this->tenancy->isFreelancer(),
            'canManage' => Gate::allows('manage-contracts'),
            'canApproveWork' => $this->tenancy->role()?->canApproveWork() ?? false,
        ]);
    }

    public function edit(Contract $contract): View
    {
        $this->authorize('manage-contracts');
        abort_unless($contract->status === 'draft', 422, 'Only drafts can be edited. Terminate and create a new contract instead.');

        return view('contracts.form', $this->formData($contract));
    }

    public function update(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('manage-contracts');
        abort_unless($contract->status === 'draft', 422, 'Only drafts can be edited.');

        $contract->update($this->validated($request));

        return redirect()->route('contracts.show', $contract)->with('status', 'Contract saved.');
    }

    public function send(Contract $contract): RedirectResponse
    {
        $this->authorize('manage-contracts');
        abort_unless($contract->status === 'draft', 422);
        abort_if($contract->type === 'milestone' && $contract->milestones()->doesntExist(), 422, 'Add at least one milestone before sending.');

        $contract->update(['status' => 'sent', 'sent_at' => now()]);

        return back()->with('status', 'Sent to '.$contract->freelancer->name.' for their agreement.');
    }

    public function accept(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorizeOwn($request, $contract);
        abort_unless($contract->status === 'sent', 422);

        $contract->update(['status' => 'active', 'accepted_at' => now(), 'accepted_ip' => $request->ip()]);
        AuditLog::record('contract.accepted', $contract);

        return back()->with('status', 'You accepted this contract. You can start logging time and invoicing.');
    }

    public function decline(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorizeOwn($request, $contract);
        abort_unless($contract->status === 'sent', 422);

        $contract->update(['status' => 'declined']);

        return back()->with('status', 'Contract declined.');
    }

    public function terminate(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('manage-contracts');
        abort_unless(in_array($contract->status, ['sent', 'active'], true), 422);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $contract->update(['status' => 'terminated', 'terminated_at' => now(), 'termination_reason' => $data['reason']]);
        AuditLog::record('contract.terminated', $contract);

        return back()->with('status', 'Contract ended.');
    }

    public function addMilestone(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('manage-contracts');
        abort_unless($contract->type === 'milestone' && in_array($contract->status, ['draft', 'sent'], true), 422, 'Milestones can only be added before the contract starts.');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'due_date' => ['nullable', 'date'],
        ]);

        $contract->milestones()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'amount_minor' => Money::toMinor($data['amount']),
            'currency' => $contract->currency,
            'due_date' => $data['due_date'] ?? null,
            'position' => $contract->milestones()->count(),
            'project_id' => $contract->project_id,
            'status' => 'pending',
        ]);

        return back()->with('status', 'Milestone added.');
    }

    public function deleteMilestone(Contract $contract, ContractMilestone $milestone): RedirectResponse
    {
        $this->authorize('manage-contracts');
        abort_unless($milestone->contract_id === $contract->id && $milestone->status === 'pending', 422);
        $milestone->delete();

        return back()->with('status', 'Milestone removed.');
    }

    public function submitMilestone(Request $request, Contract $contract, ContractMilestone $milestone): RedirectResponse
    {
        $this->authorizeOwn($request, $contract);
        abort_unless($contract->status === 'active' && $milestone->contract_id === $contract->id && $milestone->status === 'pending', 422);

        $milestone->update(['status' => 'submitted', 'submitted_at' => now()]);

        return back()->with('status', 'Milestone submitted for approval.');
    }

    public function approveMilestone(Request $request, Contract $contract, ContractMilestone $milestone): RedirectResponse
    {
        abort_unless($this->tenancy->role()?->canApproveWork(), 403);
        abort_unless($milestone->contract_id === $contract->id && $milestone->status === 'submitted', 422);

        $milestone->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        AuditLog::record('milestone.approved', $milestone);

        return back()->with('status', 'Milestone approved. The freelancer can now invoice it.');
    }

    public function reopenMilestone(Request $request, Contract $contract, ContractMilestone $milestone): RedirectResponse
    {
        abort_unless($this->tenancy->role()?->canApproveWork(), 403);
        abort_unless($milestone->contract_id === $contract->id && $milestone->status === 'submitted', 422);

        $milestone->update(['status' => 'pending', 'submitted_at' => null]);

        return back()->with('status', 'Sent back to the freelancer.');
    }

    private function authorizeAny(): void
    {
        abort_unless($this->tenancy->isFreelancer() || Gate::allows('see-money'), 403);
    }

    private function authorizeView(Request $request, Contract $contract): void
    {
        if ($this->tenancy->isFreelancer()) {
            abort_unless($contract->user_id === $request->user()->id && $contract->status !== 'draft', 404);

            return;
        }

        abort_unless(Gate::allows('see-money'), 403);
    }

    private function authorizeOwn(Request $request, Contract $contract): void
    {
        abort_unless($contract->user_id === $request->user()->id, 403);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'uuid'],
            'project_id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in(Contract::TYPES)],
            'currency' => ['required', Rule::in(array_keys(Money::CURRENCIES))],
            'hourly_rate' => ['nullable', 'required_if:type,hourly', 'numeric', 'gt:0'],
            'fixed_amount' => ['nullable', 'required_if:type,fixed', 'numeric', 'gt:0'],
            'retainer_amount' => ['nullable', 'required_if:type,retainer', 'numeric', 'gt:0'],
            'hourly_rate_retainer' => ['nullable', 'numeric', 'gt:0'],
            'max_hours_per_cycle' => ['nullable', 'numeric', 'min:0'],
            'payment_cycle' => ['required', Rule::in(['weekly', 'biweekly', 'monthly', 'on_completion'])],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:180'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'terms' => ['nullable', 'string', 'max:20000'],
        ]);

        // The person must be an active freelancer here, and the project must be one of ours.
        abort_unless(OrganizationMember::where('user_id', $data['user_id'])->where('member_type', 'freelancer')->where('status', 'active')->exists(), 422, 'Choose one of your freelancers.');
        if (! empty($data['project_id'])) {
            Project::findOrFail($data['project_id']);
        }

        $isHourlyLike = in_array($data['type'], ['hourly', 'retainer'], true);

        return [
            'user_id' => $data['user_id'],
            'project_id' => $data['project_id'] ?? null,
            'title' => $data['title'],
            'type' => $data['type'],
            'currency' => $data['currency'],
            // Hourly rate drives time billing; a retainer can carry one for any hours beyond the cap.
            'hourly_rate_minor' => Money::toMinor($data['type'] === 'hourly' ? $data['hourly_rate'] : ($data['hourly_rate_retainer'] ?? null)),
            'fixed_amount_minor' => $data['type'] === 'fixed' ? Money::toMinor($data['fixed_amount']) : null,
            'retainer_amount_minor' => $data['type'] === 'retainer' ? Money::toMinor($data['retainer_amount']) : null,
            'max_hours_per_cycle' => $isHourlyLike ? ($data['max_hours_per_cycle'] ?? null) : null,
            'payment_cycle' => $data['payment_cycle'],
            'payment_terms_days' => $data['payment_terms_days'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'] ?? null,
            'terms' => $data['terms'] ?? null,
        ];
    }

    private function formData(Contract $contract): array
    {
        return [
            'contract' => $contract,
            'freelancers' => OrganizationMember::query()->with('user:id,name')->where('member_type', 'freelancer')->where('status', 'active')->get(),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function nextReference(): string
    {
        $year = now()->year;
        $n = Contract::query()->whereYear('created_at', $year)->count() + 1;

        do {
            $ref = sprintf('CT-%d-%03d', $year, $n++);
        } while (Contract::where('reference', $ref)->exists());

        return $ref;
    }
}
