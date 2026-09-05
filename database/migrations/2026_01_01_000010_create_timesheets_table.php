<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contract_id')->nullable()->constrained()->nullOnDelete();

            $table->date('period_start');
            $table->date('period_end');
            // draft, submitted, approved, rejected, invoiced
            $table->string('status')->default('draft');

            $table->integer('total_minutes')->default(0);
            $table->bigInteger('total_amount_minor')->default(0);
            $table->char('currency', 3)->default('INR');

            $table->timestamp('submitted_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'user_id', 'period_start', 'period_end'], 'timesheets_period_unique');
            $table->index(['organization_id', 'status']);
        });

        Schema::create('time_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('timesheet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('task_id')->nullable()->constrained()->nullOnDelete();

            $table->date('entry_date');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('minutes');
            $table->text('description')->nullable();
            $table->boolean('is_billable')->default(true);
            $table->bigInteger('rate_minor')->nullable();
            $table->string('source')->default('manual'); // manual, timer

            // Set once the entry has been invoiced. Locked entries must not be edited.
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'user_id', 'entry_date']);
            $table->index(['task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('timesheets');
    }
};
