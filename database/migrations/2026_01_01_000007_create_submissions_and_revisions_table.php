<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('task_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->integer('attempt')->default(1);
            $table->text('note')->nullable();
            $table->timestamp('submitted_at');
            // pending, approved, rejected, revision_required
            $table->string('status')->default('pending');
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['task_id', 'attempt']);
        });

        // Structured revisions instead of a free-text "please fix this" comment.
        Schema::create('task_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('task_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('task_submission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('raised_by')->constrained('users')->cascadeOnDelete();

            $table->integer('number');
            $table->string('issue');
            $table->string('location')->nullable(); // "Homepage", "Section 3", file name
            $table->string('priority')->default('medium');
            $table->text('comment')->nullable();
            $table->string('status')->default('open'); // open, in_progress, resolved, dropped
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'number']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_revisions');
        Schema::dropIfExists('task_submissions');
    }
};
