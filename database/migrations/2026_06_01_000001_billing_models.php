<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->default(0)->after('unit_rate_minor');
        });

        // Saved tax settings that can be applied to an invoice in one click.
        Schema::create('tax_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->char('country_code', 2)->nullable();
            $table->string('applies_to')->default('all');       // all, domestic, international
            $table->string('treatment')->default('none');       // keys of Invoice::TREATMENTS
            $table->decimal('rate', 6, 2)->default(0);
            $table->string('label', 30)->nullable();
            $table->string('place_of_supply')->nullable();
            $table->string('sac_code')->nullable();
            $table->string('lut_reference')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Rates you enter yourself. They are suggested on an invoice, never applied silently.
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->char('from_currency', 3);
            $table->char('to_currency', 3)->default('INR');
            $table->decimal('rate', 14, 6);
            $table->date('effective_on');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'from_currency', 'to_currency', 'effective_on'], 'rates_lookup');
        });

        $now = now();
        $plans = [
            ['name' => 'Free', 'slug' => 'free', 'price_minor' => 0, 'limits' => ['clients' => 5, 'projects' => 5, 'storage_mb' => 1024, 'team_members' => 1], 'position' => 1],
            ['name' => 'Pro', 'slug' => 'pro', 'price_minor' => 49900, 'limits' => [], 'position' => 2],
            ['name' => 'Agency', 'slug' => 'agency', 'price_minor' => 149900, 'limits' => [], 'position' => 3],
        ];
        $ids = [];
        foreach ($plans as $p) {
            $ids[$p['slug']] = (string) Str::uuid();
            DB::table('plans')->insert(['id' => $ids[$p['slug']], 'name' => $p['name'], 'slug' => $p['slug'], 'price_minor' => $p['price_minor'], 'currency' => 'INR', 'interval' => 'monthly',
                'limits' => json_encode($p['limits'] ?: new stdClass), 'is_active' => true, 'position' => $p['position'], 'created_at' => $now, 'updated_at' => $now]);
        }

        // Everyone who is already here keeps working without limits.
        foreach (DB::table('organizations')->pluck('id') as $orgId) {
            DB::table('subscriptions')->insert(['id' => (string) Str::uuid(), 'organization_id' => $orgId, 'plan_id' => $ids['pro'], 'status' => 'active', 'starts_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        }

        if (DB::getDriverName() === 'pgsql') {
            foreach (['tax_profiles', 'exchange_rates'] as $t) {
                DB::statement("ALTER TABLE {$t} ENABLE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$t} FORCE ROW LEVEL SECURITY");
                DB::statement("CREATE POLICY tenant_isolation ON {$t} USING (organization_id::text = current_setting('app.current_organization_id', true)) WITH CHECK (organization_id::text = current_setting('app.current_organization_id', true))");
            }
        }
    }

    public function down(): void
    {
        DB::table('subscriptions')->delete();
        DB::table('plans')->whereIn('slug', ['free', 'pro', 'agency'])->delete();
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('tax_profiles');
        Schema::table('invoice_items', fn (Blueprint $t) => $t->dropColumn('discount_percent'));
    }
};
