<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tenant tables that get a Postgres row-level-security backstop. booking_pages is public by design and is not one of them. */
    private const TENANT = ['proposals', 'agreements', 'expenses', 'time_off', 'bookings'];

    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();   // the project made when it was accepted
            $table->string('number');
            $table->string('title');
            $table->text('intro')->nullable();
            $table->text('terms')->nullable();
            $table->string('currency', 3);
            $table->json('items');                                                           // description, quantity, unit_rate_minor
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->string('tax_label', 30)->nullable();
            $table->date('valid_until')->nullable();
            $table->string('status')->default('draft');                                      // draft, sent, accepted, declined
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('signed_name')->nullable();
            $table->string('signed_ip', 45)->nullable();
            $table->string('decline_reason')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'number']);
        });

        Schema::create('agreements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->string('status')->default('draft');                                      // draft, sent, signed, declined, void
            $table->string('body_hash', 64)->nullable();                                     // fingerprint of the text the client was shown
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_name')->nullable();
            $table->string('signed_ip', 45)->nullable();
            $table->foreignUuid('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decline_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->date('spent_on');
            $table->string('category', 40);
            $table->string('description');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->boolean('is_billable')->default(false);
            $table->foreignUuid('billed_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignUuid('receipt_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'spent_on']);
        });

        Schema::create('time_off', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('kind', 20)->default('vacation');                                 // vacation, sick, holiday, other
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'starts_on']);
        });

        Schema::create('booking_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();                                          // a call link or an address, shown to the guest
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->unsignedSmallInteger('notice_hours')->default(12);
            $table->unsignedSmallInteger('window_days')->default(30);
            $table->string('timezone')->default('UTC');
            $table->json('hours');                                                           // {"1": ["09:00", "17:00"], ...} Monday=1 ... Sunday=7
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('booking_page_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();                // the host
            $table->string('name');
            $table->string('email');
            $table->text('notes')->nullable();
            $table->dateTime('starts_at');                                                   // stored in UTC
            $table->dateTime('ends_at');
            $table->string('status')->default('confirmed');                                  // confirmed, cancelled
            $table->string('token', 40)->unique();
            $table->timestamps();
            $table->index(['user_id', 'starts_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach (self::TENANT as $t) {
                DB::statement("ALTER TABLE {$t} ENABLE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$t} FORCE ROW LEVEL SECURITY");
                DB::statement("CREATE POLICY tenant_isolation ON {$t} USING (organization_id::text = current_setting('app.current_organization_id', true)) WITH CHECK (organization_id::text = current_setting('app.current_organization_id', true))");
            }
        }
    }

    public function down(): void
    {
        foreach (['bookings', 'booking_pages', 'time_off', 'expenses', 'agreements', 'proposals'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
