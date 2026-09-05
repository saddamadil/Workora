<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Support\Tenancy;

/**
 * Invoices have an asymmetry worth being explicit about: the freelancer owns the
 * document until it is submitted, and the company owns the decision afterwards.
 * Neither side can edit an approved invoice — corrections are a credit note or a
 * new invoice, so the financial trail stays intact.
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

        return $role->seesMoney();
    }

    public function create(User $user): bool
    {
        return $this->tenancy->role()?->isFreelancer() ?? false;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->user_id === $user->id
            && in_array($invoice->status, ['draft', 'rejected'], true);
    }

    public function submit(User $user, Invoice $invoice): bool
    {
        return $invoice->user_id === $user->id
            && $invoice->status === 'draft'
            && $invoice->items()->exists();
    }

    public function approve(User $user, Invoice $invoice): bool
    {
        $role = $this->tenancy->role();

        return $role !== null
            && $role->canApprovePayment()
            && in_array($invoice->status, ['submitted', 'under_review'], true)
            // The person who raised it cannot approve it, even if they somehow
            // hold both roles in this company.
            && $invoice->user_id !== $user->id;
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
        // Approved invoices are financial records. Void them, never delete them.
        return $invoice->user_id === $user->id && $invoice->status === 'draft';
    }
}
