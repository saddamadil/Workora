<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete(); // issuing freelancer
            $table->foreignUuid('contract_id')->nullable()->constrained()->nullOnDelete();

            $table->string('number');
            $table->date('issue_date');
            $table->date('due_date');
            $table->char('currency', 3)->default('INR');

            $table->bigInteger('subtotal_minor')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->bigInteger('amount_paid_minor')->default(0);

            // draft, submitted, under_review, approved, rejected, partially_paid, paid, void
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->string('pdf_path')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained()->cascadeOnDelete();

            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit')->default('hours'); // hours, items, fixed
            $table->bigInteger('unit_rate_minor')->default(0);
            $table->bigInteger('amount_minor')->default(0);

            // What this line was generated from: task, contract_milestone, timesheet
            $table->string('source_type')->nullable();
            $table->uuid('source_id')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });

        // A record of money moving. This application tracks and schedules payments;
        // it does not process them. Settlement happens through a licensed processor.
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete(); // payee
            $table->foreignUuid('payout_method_id')->nullable()->constrained()->nullOnDelete();

            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('INR');
            $table->string('method')->default('bank_transfer');
            // pending, scheduled, processing, paid, failed, disputed, cancelled
            $table->string('status')->default('pending');

            $table->date('scheduled_for')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('reference')->nullable();
            $table->string('processor')->nullable();
            $table->string('processor_reference')->nullable();
            $table->text('failure_reason')->nullable();
            $table->text('notes')->nullable();

            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('scheduled_for');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
