<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Company-defined freelancer categories (Developer, Designer, SEO, ...).
        Schema::create('freelancer_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('colour', 7)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        // The single join between a user and a company. A freelancer has one row
        // per company they work with; an employee has exactly one.
        Schema::create('organization_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            // owner, admin, project_manager, team_member, finance, viewer, freelancer
            $table->string('role');
            $table->string('member_type')->default('employee'); // employee, freelancer
            // invited, active, on_hold, inactive, blocked
            $table->string('status')->default('invited');

            // Freelancer-specific, per company.
            $table->foreignUuid('freelancer_category_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('default_rate_minor')->nullable();
            $table->char('default_rate_currency', 3)->nullable();
            $table->text('internal_notes')->nullable();

            $table->foreignUuid('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
            $table->index(['organization_id', 'member_type', 'status']);
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role');
            $table->string('member_type')->default('employee');
            $table->string('token', 64)->unique();
            $table->foreignUuid('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('freelancer_categories');
    }
};
