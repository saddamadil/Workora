<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();           // who issues them
            $table->foreignUuid('client_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('frequency');                                                // weekly, monthly, quarterly, yearly
            $table->date('next_run_on');
            $table->date('ends_on')->nullable();
            $table->boolean('auto_send')->default(false);
            $table->string('status')->default('active');                                // active, paused, ended
            $table->json('details');                                                    // currency, tax, template, terms, notes ...
            $table->json('items');                                                      // description, quantity, unit, unit_rate_minor, discount_percent
            $table->date('last_run_on')->nullable();
            $table->unsignedInteger('runs_count')->default(0);
            $table->timestamps();
            $table->index(['status', 'next_run_on']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE recurring_invoices ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE recurring_invoices FORCE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_isolation ON recurring_invoices USING (organization_id::text = current_setting('app.current_organization_id', true)) WITH CHECK (organization_id::text = current_setting('app.current_organization_id', true))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_invoices');
    }
};
