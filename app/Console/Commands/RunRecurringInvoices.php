<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\RecurringInvoice;
use App\Services\RecurringInvoices;
use App\Support\Tenancy;
use Illuminate\Console\Command;

class RunRecurringInvoices extends Command
{
    protected $signature = 'invoices:recurring';

    protected $description = 'Create the invoices that are due from recurring schedules';

    public function handle(Tenancy $tenancy, RecurringInvoices $service): int
    {
        $made = 0;
        foreach (Organization::query()->get() as $org) {
            $tenancy->forOrganization($org, function () use ($service, &$made) {
                $due = RecurringInvoice::query()->where('status', 'active')->whereDate('next_run_on', '<=', now()->toDateString())->get();
                foreach ($due as $r) {
                    if ($r->ends_on && $r->next_run_on->gt($r->ends_on)) {
                        $r->update(['status' => 'ended']);

                        continue;
                    }
                    try {
                        $service->run($r);
                        $made++;
                    } catch (\Throwable $e) {
                        report($e);   // one broken schedule must not stop the others
                    }
                }
            });
        }
        $this->info("{$made} invoice(s) created.");

        return self::SUCCESS;
    }
}
