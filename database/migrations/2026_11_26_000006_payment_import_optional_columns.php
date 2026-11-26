<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments import: a payment no longer names an invoice (it settles the oldest unpaid invoice first), and a row
 * that cannot be read is kept with its reason for the review page, so these staging columns may be empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('temp_tenant_payments')) {
            return;
        }
        Schema::table('temp_tenant_payments', function (Blueprint $table) {
            $table->string('invoice_id')->nullable()->change();
            $table->date('payment_date')->nullable()->change();
            $table->string('payment_method')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Staging rows are temporary; the columns stay optional.
    }
};
