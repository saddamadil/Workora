<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Postgres row level security, used as a backstop under the application-level
 * tenant scope — not as a replacement for it.
 *
 * Read docs/TENANCY.md before changing anything here. Two things matter:
 *
 * 1. RLS is bypassed by the table owner unless FORCE is set. The application
 *    must connect as a role that is NOT the owner of these tables.
 * 2. The policy reads a session variable. If SetCurrentOrganization middleware
 *    does not set it, every tenant query returns zero rows — a loud failure,
 *    which is the behaviour we want.
 */
return new class extends Migration
{
    /** Every table carrying an organization_id column. */
    private array $tables = [
        'organization_members', 'freelancer_categories', 'invitations',
        'clients', 'projects', 'project_members',
        'tasks', 'task_assignees', 'task_comments', 'task_checklist_items',
        'task_dependencies', 'task_submissions', 'task_revisions',
        'work_requests', 'work_request_messages',
        'contracts', 'contract_milestones',
        'timesheets', 'time_entries',
        'invoices', 'invoice_items', 'payments',
        'files', 'audit_logs', 'subscriptions',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY tenant_isolation ON {$table}
                USING (organization_id::text = current_setting('app.current_organization_id', true))
                WITH CHECK (organization_id::text = current_setting('app.current_organization_id', true))
            ");
        }

        // audit_logs is append-only. No UPDATE or DELETE, by anyone, ever.
        DB::statement("
            CREATE OR REPLACE FUNCTION audit_logs_are_immutable()
            RETURNS trigger AS \$\$
            BEGIN
                RAISE EXCEPTION 'audit_logs rows cannot be modified or deleted';
            END;
            \$\$ LANGUAGE plpgsql
        ");

        DB::statement("
            CREATE TRIGGER audit_logs_immutable
            BEFORE UPDATE OR DELETE ON audit_logs
            FOR EACH ROW EXECUTE FUNCTION audit_logs_are_immutable()
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS audit_logs_immutable ON audit_logs');
        DB::statement('DROP FUNCTION IF EXISTS audit_logs_are_immutable()');

        foreach ($this->tables as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
