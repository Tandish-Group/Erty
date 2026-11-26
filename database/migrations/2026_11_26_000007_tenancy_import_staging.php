<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenants import brings in tenancies, not only tenant profiles: each staged tenant row has a staged tenancy with
 * the property, unit, rent, deposit held and the balance owed when it moves to Erty. Rows that cannot be read are
 * kept with their reason for the review page, so the tenancy and notice staging columns may be empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('temp_tenant_management')) {
            Schema::table('temp_tenant_management', function (Blueprint $table) {
                foreach (['property' => fn () => $table->string('property')->nullable()->after('temp_tenant_profile_id'),
                    'currency' => fn () => $table->string('currency', 3)->nullable(),
                    'service_charge_per_sq_ft' => fn () => $table->decimal('service_charge_per_sq_ft', 10, 2)->nullable(),
                    'deposit_held' => fn () => $table->decimal('deposit_held', 15, 2)->nullable(),
                    'balance_owed' => fn () => $table->decimal('balance_owed', 15, 2)->nullable()] as $column => $add) {
                    if (!Schema::hasColumn('temp_tenant_management', $column)) {
                        $add();
                    }
                }
            });
            Schema::table('temp_tenant_management', function (Blueprint $table) {
                $table->string('listing_type')->nullable()->change();
                $table->string('complex_slug')->nullable()->change();
                $table->string('agreement_type')->nullable()->change();
                $table->date('start_date')->nullable()->change();
                $table->decimal('monthly_payment', 15, 2)->nullable()->change();
            });
        }
        if (Schema::hasTable('temp_tenant_notices')) {
            Schema::table('temp_tenant_notices', function (Blueprint $table) {
                $table->string('notice_type')->nullable()->change();
                $table->date('notice_date')->nullable()->change();
                $table->date('effective_date')->nullable()->change();
                $table->string('notice_subject')->nullable()->change();
                $table->text('notice_details')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Staging rows are temporary; the added columns are harmless and stay.
    }
};
