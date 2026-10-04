<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Task;
use App\Services\ClientContext;
use App\Services\Notifier;
use App\Support\Tenancy;
use Illuminate\Console\Command;

/** Daily: invoices coming due or overdue, and tasks due today. Safe to run more than once a day. */
class SendDueReminders extends Command
{
    protected $signature = 'reminders:due';

    protected $description = 'Notify people about invoices that are due soon or overdue, and tasks due today';

    public function handle(Tenancy $tenancy, Notifier $notifier, ClientContext $ctx): int
    {
        $sent = 0;

        foreach (Organization::query()->get() as $org) {
            $tenancy->forOrganization($org, function () use ($ctx, $notifier, &$sent) {
                $open = Invoice::query()->whereIn('status', ['submitted', 'under_review', 'approved', 'partially_paid'])->get();

                foreach ($open as $invoice) {
                    $client = $invoice->client_id ? Client::query()->find($invoice->client_id) : null;
                    $clientUsers = $client ? $ctx->clientUsers($client) : collect();
                    $owner = \App\Models\User::query()->find($invoice->user_id);
                    $daysLeft = (int) now()->startOfDay()->diffInDays($invoice->due_date->startOfDay(), false);

                    if ($daysLeft < 0) {
                        foreach ($clientUsers as $u) {
                            $notifier->once($u, 'invoice', 'Overdue: invoice '.$invoice->number, money($invoice->outstandingMinor(), $invoice->currency).' was due '.$invoice->due_date->format('d M'), route('portal.invoice', $invoice), 7);
                            $sent++;
                        }
                        $owner && $notifier->once($owner, 'invoice', 'Overdue: invoice '.$invoice->number, $client?->name, route('invoices.show', $invoice), 7);
                    } elseif ($daysLeft <= 3) {
                        foreach ($clientUsers as $u) {
                            $notifier->once($u, 'invoice', 'Due soon: invoice '.$invoice->number, 'Due '.$invoice->due_date->format('d M'), route('portal.invoice', $invoice), 3);
                            $sent++;
                        }
                        $owner && $notifier->once($owner, 'invoice', 'Due soon: invoice '.$invoice->number, $client?->name, route('invoices.show', $invoice), 3);
                    }
                }

                $tasks = Task::query()->with('assignees:id,name,email')->whereNotIn('status', ['approved', 'cancelled'])->whereDate('due_at', now()->toDateString())->get();
                foreach ($tasks as $task) {
                    foreach ($task->assignees as $u) {
                        $notifier->once($u, 'task', 'Due today: '.$task->title, null, route('tasks.show', $task), 1);
                        $sent++;
                    }
                }
            });
        }

        $this->info("Checked. {$sent} reminder(s) considered.");

        return self::SUCCESS;
    }
}
