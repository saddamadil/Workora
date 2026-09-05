<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete(); // the freelancer
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();

            $table->string('reference');
            $table->string('title');
            $table->string('type'); // hourly, fixed, milestone, retainer
            $table->char('currency', 3)->default('INR');

            $table->bigInteger('hourly_rate_minor')->nullable();
            $table->bigInteger('fixed_amount_minor')->nullable();
            $table->bigInteger('retainer_amount_minor')->nullable();
            $table->decimal('max_hours_per_cycle', 8, 2)->nullable();

            // weekly, biweekly, monthly, on_milestone, on_completion
            $table->string('payment_cycle')->default('monthly');
            $table->integer('payment_terms_days')->default(15);

            $table->date('starts_on');
            $table->date('ends_on')->nullable();

            // draft, sent, active, paused, completed, terminated, declined
            $table->string('status')->default('draft');
            $table->longText('terms')->nullable();
            $table->string('document_path')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('accepted_ip', 45)->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->text('termination_reason')->nullable();

            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'reference']);
            $table->index(['organization_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('contract_milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('INR');
            $table->date('due_date')->nullable();
            $table->integer('position')->default(0);

            // pending, in_progress, submitted, approved, rejected, invoiced, paid
            $table->string('status')->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_milestones');
        Schema::dropIfExists('contracts');
    }
};
