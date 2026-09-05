<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->bigInteger('price_minor')->default(0);
            $table->char('currency', 3)->default('INR');
            $table->string('interval')->default('monthly'); // monthly, yearly
            $table->jsonb('limits')->default('{}'); // max_users, max_freelancers, max_projects
            $table->boolean('is_active')->default(true);
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->string('website')->nullable();
            $table->string('industry')->nullable();

            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->char('country_code', 2)->nullable();

            // GST / VAT / other registration number. Not a payment credential.
            $table->string('tax_identifier')->nullable();
            $table->decimal('default_tax_rate', 5, 2)->default(0);

            $table->char('base_currency', 3)->default('INR');
            $table->string('timezone')->default('Asia/Kolkata');
            $table->jsonb('settings')->default('{}');

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained();
            $table->string('status')->default('trialing'); // trialing, active, past_due, cancelled
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            // Identifier issued by the payment processor. No card or bank data is stored here.
            $table->string('processor')->nullable();
            $table->string('processor_reference')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('plans');
    }
};
