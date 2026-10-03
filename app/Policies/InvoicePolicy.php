<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Tenancy;

/**
 * Freelancers issue their own invoices. Finance roles (owner, admin, finance) may also prepare
 * one on a freelancer's behalf; the freelancer still owns it, and whoever prepared it cannot be
 * the person who approves it.
 */
class InvoicePolicy
{
    public function __construct(private Tenancy $tenancy) {}

    public function viewAny(User $user): bool
    {
        $role = $this->tenancy->role();

        return $role !== null && ($role->seesMoney() || $role->isFreelancer());
    }

    public function view(User $user, Invoice $invoice): bool
    {
        $role = $this->tenancy->role();

        if ($role === null) {
            return false;
        }

        if ($role->isFreelancer()) {
            return $invoice->user_id === $user->id;
        }

        // Someone else's draft is private until it is sent, unless this person prepared it.
        if ($invoice->status === 'draft' && $invoice->prepared_by !== $user->id) {
            return false;
        }

        return $role->seesMoney();
    }

    /** May start an invoice at all (for themselves, or for a freelancer). */
    public function create(User $user): bool
    {
        $role = $this->tenancy->role();

        return $role !== null && ($role->isFreelancer() || Permissions::allows('manage-contracts', $role));
    }

    /** May issue an invoice in this particular freelancer's name. */
    public function createFor(User $user, string $freelancerId): bool
    {
        if (! $this->create($user)) {
            return false;
        }

        if ($this->tenancy->isFreelancer()) {
            return $freelancerId === $user->id;
        }

        return OrganizationMember::query()->where('user_id', $freelancerId)->where('member_type', 'freelancer')->where('status', 'active')->exists();
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->owns($user, $invoice) && in_array($invoice->status, ['draft', 'rejected'], true);
    }

    public function submit(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice) && $invoice->items()->exists();
    }

    public function approve(User $user, Invoice $invoice): bool
    {
        $role = $this->tenancy->role();

        return $role !== null
            && $role->canApprovePayment()
            && in_array($invoice->status, ['submitted', 'under_review'], true)
            && $invoice->user_id !== $user->id
            && $invoice->prepared_by !== $user->id;
    }

    public function reject(User $user, Invoice $invoice): bool
    {
        return $this->approve($user, $invoice);
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        $role = $this->tenancy->role();

        return $role !== null
            && $role->canApprovePayment()
            && in_array($invoice->status, ['approved', 'partially_paid'], true);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->owns($user, $invoice) && $invoice->status === 'draft';
    }

    /** A copy is a new draft, so it follows the rules for starting one. */
    public function duplicate(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice) && $this->createFor($user, $invoice->user_id);
    }

    /** The freelancer who issues it, or the person who prepared it for them. */
    private function owns(User $user, Invoice $invoice): bool
    {
        return $invoice->user_id === $user->id || $invoice->prepared_by === $user->id;
    }
}
