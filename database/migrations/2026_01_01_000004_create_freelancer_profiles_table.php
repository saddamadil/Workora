<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One profile per freelancer, shared across every company they work with.
        Schema::create('freelancer_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('headline')->nullable();
            $table->text('bio')->nullable();
            $table->decimal('years_experience', 4, 1)->nullable();
            $table->bigInteger('default_hourly_rate_minor')->nullable();
            $table->char('default_currency', 3)->default('INR');
            $table->string('availability')->default('available'); // available, limited, unavailable
            $table->string('portfolio_url')->nullable();
            $table->string('resume_path')->nullable();
            $table->string('tax_identifier')->nullable();
            $table->boolean('is_public')->default(false);

            $table->timestamps();
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('freelancer_skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('skill_id')->constrained()->cascadeOnDelete();
            $table->string('level')->nullable(); // beginner, intermediate, expert
            $table->timestamps();

            $table->unique(['freelancer_profile_id', 'skill_id']);
        });

        // Where a freelancer wants to be paid. Store a processor token or a
        // human-readable label only. Never raw card or full bank credentials.
        Schema::create('payout_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // bank_transfer, upi, paypal, wise, other
            $table->string('label');
            $table->char('currency', 3)->default('INR');
            $table->text('details_encrypted')->nullable(); // Laravel encrypted cast
            $table->string('processor')->nullable();
            $table->string('processor_reference')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_methods');
        Schema::dropIfExists('freelancer_skills');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('freelancer_profiles');
    }
};
