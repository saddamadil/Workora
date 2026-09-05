<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The freelancer-initiated half of the workflow. This is the part of the
        // product that competitors mostly do not have, so it ships in v1.
        Schema::create('work_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->text('description');
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->bigInteger('proposed_amount_minor')->nullable();
            $table->char('currency', 3)->default('INR');
            $table->date('proposed_deadline')->nullable();

            // draft, submitted, under_review, negotiating, approved, rejected, withdrawn, converted
            $table->string('status')->default('draft');
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('response_note')->nullable();

            // Set when the request becomes real work.
            $table->foreignUuid('converted_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignUuid('converted_project_id')->nullable()->constrained('projects')->nullOnDelete();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['requested_by', 'status']);
        });

        // Negotiation thread. A message may carry a revised price or scope.
        Schema::create('work_request_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('work_request_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->bigInteger('proposed_amount_minor')->nullable();
            $table->decimal('proposed_hours', 8, 2)->nullable();
            $table->date('proposed_deadline')->nullable();
            $table->timestamps();

            $table->index(['work_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_request_messages');
        Schema::dropIfExists('work_requests');
    }
};
