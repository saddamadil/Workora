<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** New tenant tables that get the same Postgres backstop as the rest. */
    private array $tenantTables = [
        'messages', 'conversation_reads', 'project_milestones', 'deliverables', 'deliverable_reviews',
        'client_requests', 'app_notifications', 'calendar_events', 'payment_reports',
    ];

    public function up(): void
    {
        // A conversation is one client, optionally narrowed to one project. Messages never mix projects.
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->uuid('invoice_id')->nullable();
            $table->text('body');
            $table->boolean('is_important')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['client_id', 'project_id', 'created_at']);
        });

        Schema::create('conversation_reads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 40);            // the project id, or 'general'
            $table->timestamp('last_read_at');
            $table->unique(['user_id', 'client_id', 'scope']);
        });

        Schema::create('project_milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('upcoming');   // upcoming, in_progress, completed
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'position']);
        });

        Schema::create('deliverables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->uuid('task_id')->nullable();
            $table->uuid('milestone_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('in_review'); // in_review, approved, changes_requested
            $table->foreignUuid('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status']);
        });

        // Every decision is kept, so a deliverable has a full revision history.
        Schema::create('deliverable_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('deliverable_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('decision');                      // approved, changes_requested
            $table->text('comment')->nullable();
            $table->unsignedInteger('round')->default(1);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('client_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('medium');
            $table->date('preferred_deadline')->nullable();
            $table->string('status')->default('new');        // new, discussing, accepted, in_progress, completed, declined
            $table->text('response_note')->nullable();
            $table->uuid('task_id')->nullable();
            $table->uuid('converted_project_id')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'status']);
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 60);
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'read_at', 'created_at']);
        });

        Schema::create('calendar_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('kind')->default('meeting');     // meeting, reminder
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('visible_to_client')->default(false);
            $table->timestamps();
            $table->index(['organization_id', 'starts_at']);
        });

        Schema::create('payment_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('reported_by')->constrained('users')->cascadeOnDelete();
            $table->bigInteger('amount_minor');
            $table->string('method')->default('bank_transfer');
            $table->string('reference', 120)->nullable();
            $table->date('paid_on');
            $table->string('status')->default('pending');   // pending, confirmed, rejected
            $table->timestamps();
        });

        Schema::table('files', function (Blueprint $table) {
            $table->uuid('client_id')->nullable()->after('project_id');
            $table->boolean('visible_to_client')->default(false)->after('visibility');
            $table->index('client_id');
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->uuid('project_id')->nullable();
            $table->uuid('client_id')->nullable();
            $table->index(['project_id', 'created_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable();
            $table->json('notification_prefs')->nullable();
            $table->json('dashboard_widgets')->nullable();
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('is_internal')->default(false);   // hidden from the client portal
            $table->uuid('milestone_id')->nullable();
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->string('billing_model')->default('hourly');    // hourly, fixed, recurring
            $table->string('priority')->default('medium');
            $table->string('tags')->nullable();
            $table->boolean('share_hours')->default(false);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('project_id')->nullable();
        });
        Schema::table('time_entries', function (Blueprint $table) {
            $table->uuid('client_id')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach ($this->tenantTables as $t) {
                DB::statement("ALTER TABLE {$t} ENABLE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$t} FORCE ROW LEVEL SECURITY");
                DB::statement("CREATE POLICY tenant_isolation ON {$t} USING (organization_id::text = current_setting('app.current_organization_id', true)) WITH CHECK (organization_id::text = current_setting('app.current_organization_id', true))");
            }
        }
    }

    public function down(): void
    {
        Schema::table('time_entries', fn (Blueprint $t) => $t->dropColumn('client_id'));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('project_id'));
        Schema::table('projects', fn (Blueprint $t) => $t->dropColumn(['billing_model', 'priority', 'tags', 'share_hours']));
        Schema::table('tasks', fn (Blueprint $t) => $t->dropColumn(['is_internal', 'milestone_id']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['last_seen_at', 'notification_prefs', 'dashboard_widgets']));
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->dropIndex(['project_id', 'created_at']);
            $t->dropColumn(['project_id', 'client_id']);
        });
        Schema::table('files', function (Blueprint $t) {
            $t->dropIndex(['client_id']);
            $t->dropColumn(['client_id', 'visible_to_client']);
        });
        foreach (['payment_reports', 'calendar_events', 'app_notifications', 'client_requests', 'deliverable_reviews', 'deliverables', 'project_milestones', 'conversation_reads', 'messages'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
