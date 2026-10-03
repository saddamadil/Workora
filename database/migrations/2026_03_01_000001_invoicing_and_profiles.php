<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The company's invoice identity. Photos and logos are files; tax numbers vary by
        // country, so they live in one JSON map keyed by what each country calls them.
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('email')->nullable()->after('legal_name');
            $table->string('phone', 40)->nullable()->after('email');
            $table->json('tax_ids')->nullable();
        });

        // The photo is users.avatar_path; the rest of a freelancer's invoice identity is here.
        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->string('address_line1')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->json('tax_ids')->nullable();
            $table->string('website')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('freelancer_code', 12)->nullable()->unique();
            $table->string('signature_path')->nullable();
            $table->string('invoice_prefix', 10)->default('INV');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('legal_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->json('tax_ids')->nullable();
            $table->char('default_currency', 3)->nullable();
            $table->string('payment_method')->nullable();
        });

        // A payment profile is a payout method with a kind (India or international) and a country.
        Schema::table('payout_methods', function (Blueprint $table) {
            $table->string('kind')->default('domestic')->after('user_id'); // domestic, international
            $table->char('country_code', 2)->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_type')->default('domestic')->after('number'); // domestic, international
            $table->string('bill_to_type')->default('company'); // company, client
            $table->foreignUuid('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('payout_method_id')->nullable()->constrained('payout_methods')->nullOnDelete();

            $table->date('service_period_start')->nullable();
            $table->date('service_period_end')->nullable();
            $table->string('payment_terms', 60)->nullable();
            $table->string('template', 20)->default('professional');

            $table->string('tax_treatment', 30)->default('none');
            $table->json('tax_lines')->nullable(); // [{"label":"CGST","rate":9}, ...]
            $table->string('place_of_supply')->nullable();
            $table->string('sac_code', 20)->nullable();

            $table->decimal('exchange_rate', 14, 6)->nullable();
            $table->bigInteger('inr_equivalent_minor')->nullable();
            $table->string('lut_reference')->nullable();

            // Numbering: sequential per freelancer per financial year.
            $table->string('fiscal_year', 9)->nullable();
            $table->unsignedInteger('sequence')->nullable();

            $table->timestamp('sent_at')->nullable();
            // Frozen copy of the parties' details taken when the invoice is sent, so a later
            // profile edit cannot change a document that has already been issued.
            $table->json('snapshot')->nullable();
        });

        // Two freelancers in one company may both have an "INV-2026-27-001".
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'number']);
            $table->unique(['organization_id', 'user_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'user_id', 'number']);
            $table->unique(['organization_id', 'number']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropConstrainedForeignId('prepared_by');
            $table->dropConstrainedForeignId('payout_method_id');
            $table->dropColumn(['invoice_type', 'bill_to_type', 'service_period_start', 'service_period_end', 'payment_terms', 'template',
                'tax_treatment', 'tax_lines', 'place_of_supply', 'sac_code', 'exchange_rate', 'inr_equivalent_minor', 'lut_reference',
                'fiscal_year', 'sequence', 'sent_at', 'snapshot']);
        });
        Schema::table('payout_methods', fn (Blueprint $t) => $t->dropColumn(['kind', 'country_code']));
        Schema::table('clients', fn (Blueprint $t) => $t->dropColumn(['legal_name', 'logo_path', 'city', 'state', 'postal_code', 'country_code', 'tax_ids', 'default_currency', 'payment_method']));
        Schema::table('freelancer_profiles', fn (Blueprint $t) => $t->dropUnique(['freelancer_code']));
        Schema::table('freelancer_profiles', fn (Blueprint $t) => $t->dropColumn(['address_line1', 'city', 'state', 'postal_code', 'country_code', 'tax_ids', 'website', 'linkedin_url', 'freelancer_code', 'signature_path', 'invoice_prefix']));
        Schema::table('organizations', fn (Blueprint $t) => $t->dropColumn(['legal_name', 'email', 'phone', 'tax_ids']));
    }
};
