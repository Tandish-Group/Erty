<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline: House sales.
 * Generated from the schema of a running installation; each table is skipped when it already exists,
 * so existing installations can run it safely. Foreign keys are added by the last baseline migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('property_sales')) {
            Schema::create('property_sales', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('business_id');
                $table->bigInteger('complex_id');
                $table->bigInteger('residential_unit_id');
                $table->bigInteger('listing_id')->nullable();
                $table->bigInteger('lead_id')->nullable();
                $table->bigInteger('agent_id')->nullable();
                $table->string('reference', 30);
                $table->string('buyer_name');
                $table->string('buyer_phone', 40)->nullable();
                $table->string('buyer_email')->nullable();
                $table->string('buyer_id_number', 60)->nullable();
                $table->string('buyer_kra_pin', 30)->nullable();
                $table->decimal('price', 15, 2);
                $table->string('currency', 8)->default('KES');
                $table->date('agreement_date');
                $table->decimal('deposit_amount', 15, 2)->default(0);
                $table->string('status', 12)->default('reserved');
                $table->timestamp('completed_at')->nullable();
                $table->string('completion_note', 1000)->nullable();
                $table->date('title_transferred_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancel_reason', 1000)->nullable();
                $table->text('notes')->nullable();
                $table->bigInteger('created_by')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['business_id', 'status'], 'property_sales_business_id_status_index');
                $table->unique(['reference'], 'property_sales_reference_unique');
                $table->index(['residential_unit_id', 'status'], 'property_sales_residential_unit_id_status_index');
            });
        }

        if (!Schema::hasTable('property_sale_instalments')) {
            Schema::create('property_sale_instalments', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('sale_id');
                $table->smallInteger('sequence');
                $table->string('number', 40);
                $table->string('label', 80);
                $table->date('due_date');
                $table->decimal('amount', 15, 2);
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->string('status', 12)->default('due');
                $table->timestamp('overdue_notified_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['number'], 'property_sale_instalments_number_unique');
                $table->unique(['sale_id', 'sequence'], 'property_sale_instalments_sale_id_sequence_unique');
            });
        }

        if (!Schema::hasTable('property_sale_payments')) {
            Schema::create('property_sale_payments', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('sale_id');
                $table->string('receipt_number', 40);
                $table->date('paid_on');
                $table->decimal('amount', 15, 2);
                $table->string('method', 30);
                $table->string('reference', 120)->nullable();
                $table->string('status', 12)->default('confirmed');
                $table->bigInteger('recorded_by')->nullable();
                $table->bigInteger('reversed_by')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->string('reversal_reason', 1000)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['receipt_number'], 'property_sale_payments_receipt_number_unique');
            });
        }

        if (!Schema::hasTable('property_sale_payment_allocations')) {
            Schema::create('property_sale_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('payment_id');
                $table->bigInteger('instalment_id');
                $table->decimal('amount', 15, 2);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('property_sale_payment_allocations');
        Schema::dropIfExists('property_sale_payments');
        Schema::dropIfExists('property_sale_instalments');
        Schema::dropIfExists('property_sales');
        Schema::enableForeignKeyConstraints();
    }
};
