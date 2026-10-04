<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // 'solo' = one freelancer running their own business; 'team' = a company with staff.
            $table->string('mode')->default('team')->after('slug');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('type')->default('company')->after('name');      // company | individual
            $table->string('status')->default('active')->after('type');     // active | inactive
            $table->string('website')->nullable()->after('phone');
        });

        Schema::table('organization_members', function (Blueprint $table) {
            // A client login sees only this one client's projects and invoices.
            $table->foreignUuid('client_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->foreignUuid('client_id')->nullable()->after('organization_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
        Schema::table('organization_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['type', 'status', 'website']);
        });
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
