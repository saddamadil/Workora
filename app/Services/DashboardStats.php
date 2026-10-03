<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use App\Models\User;
use App\Support\Tenancy;

/** The numbers on the home screen, shaped for either a freelancer or a staff member. */
class DashboardStats
{
    public function __construct(private Tenancy $tenancy) {}

    public function for(User $user): array
    {
        return $this->tenancy->isFreelancer() ? $this->freelancer($user) : $this->staff($user);
    }

    private function staff(User $user): array
    {
        $role = $this->tenancy->role();
        $monthStart = now()->startOfMonth();
        $tasks = fn () => Task::query()->visibleTo($user);
        $money = $role->seesMoney();

        return [
            'mode' => 'staff',
            'activeProjects' => Project::query()->visibleTo($user)->where('status', 'active')->count(),
            'overdue' => $tasks()->whereNotIn('status', ['approved', 'cancelled'])->where('due_at', '<', now())->count(),
            'toReview' => $tasks()->with('project:id,name,slug', 'assignees:id,name')->whereIn('status', ['submitted', 'under_review'])->latest('updated_at')->limit(6)->get(),
            'dueSoon' => $tasks()->with('project:id,name,slug', 'assignees:id,name')->whereNotIn('status', ['approved', 'cancelled'])
                ->whereBetween('due_at', [now(), now()->addDays(7)])->orderBy('due_at')->limit(6)->get(),
            'timesheetsPending' => $role->canApproveWork() ? Timesheet::where('status', 'submitted')->count() : null,
            'hoursThisMonth' => (int) TimeEntry::where('entry_date', '>=', $monthStart)->sum('minutes'),
            'invoicesPending' => $money ? Invoice::whereIn('status', ['submitted', 'under_review'])->count() : null,
            'payable' => $money ? (int) Invoice::whereIn('status', ['approved', 'partially_paid'])->selectRaw('coalesce(sum(total_minor - amount_paid_minor),0) as due')->value('due') : null,
            'paidThisMonth' => $money ? (int) Payment::where('status', 'paid')->where('paid_at', '>=', $monthStart)->sum('amount_minor') : null,
            'contractsPending' => $money ? Contract::where('status', 'sent')->count() : null,
            'currency' => $this->tenancy->organization()->base_currency,
        ];
    }

    private function freelancer(User $user): array
    {
        $weekStart = now()->startOfWeek();
        $monthStart = now()->startOfMonth();
        $mine = fn () => Task::query()->visibleTo($user);

        // Approved time that has not been put on an invoice yet is money the freelancer is owed.
        $unbilled = TimeEntry::where('user_id', $user->id)->where('is_billable', true)->whereNull('locked_at')
            ->whereHas('timesheet', fn ($q) => $q->where('status', 'approved'))->get()
            ->sum(fn (TimeEntry $e) => $e->amountMinor());

        return [
            'mode' => 'freelancer',
            'openTasks' => $mine()->with('project:id,name,slug')->whereNotIn('status', ['approved', 'cancelled'])->orderByRaw('due_at is null')->orderBy('due_at')->limit(8)->get(),
            'needChanges' => $mine()->where('status', 'revision_required')->count(),
            'inReview' => $mine()->whereIn('status', ['submitted', 'under_review'])->count(),
            'hoursThisWeek' => (int) TimeEntry::where('user_id', $user->id)->where('entry_date', '>=', $weekStart)->sum('minutes'),
            'hoursThisMonth' => (int) TimeEntry::where('user_id', $user->id)->where('entry_date', '>=', $monthStart)->sum('minutes'),
            'unbilledMinor' => (int) $unbilled,
            'awaitingPayment' => (int) Invoice::where('user_id', $user->id)->whereIn('status', ['approved', 'partially_paid'])->selectRaw('coalesce(sum(total_minor - amount_paid_minor),0) as due')->value('due'),
            'inApproval' => Invoice::where('user_id', $user->id)->whereIn('status', ['submitted', 'under_review'])->count(),
            'paidThisMonth' => (int) Payment::where('user_id', $user->id)->where('status', 'paid')->where('paid_at', '>=', $monthStart)->sum('amount_minor'),
            'contractsToAccept' => Contract::where('user_id', $user->id)->where('status', 'sent')->count(),
            'currency' => $this->tenancy->organization()->base_currency,
        ];
    }
}
