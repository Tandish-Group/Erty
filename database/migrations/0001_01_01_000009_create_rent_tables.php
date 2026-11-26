<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline: Rent: billing periods, invoices, payments, allocations, late fees and payment reconciliation.
 * Generated from the schema of a running installation; each table is skipped when it already exists,
 * so existing installations can run it safely. Foreign keys are added by the last baseline migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenant_billings')) {
            Schema::create('tenant_billings', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('tenancy_id');
                $table->string('tenancy_ref');
                $table->string('currency', 10)->default('USD');
                $table->date('billing_date');
                $table->string('billing_type')->default('tenancy_only');
                $table->bigInteger('tenancy_billing_id');
                $table->decimal('total_amount', 15, 2);
                $table->string('status')->default('Pending');
                $table->text('system_notes')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['billing_date'], 'tenant_billings_billing_date_index');
                $table->index(['status'], 'tenant_billings_status_index');
                $table->index(['tenancy_id'], 'tenant_billings_tenancy_id_index');
                $table->index(['tenancy_ref'], 'tenant_billings_tenancy_ref_index');
            });
        }

        if (!Schema::hasTable('tenant_tenancy_billings')) {
            Schema::create('tenant_tenancy_billings', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('tenancy_id');
                $table->string('tenancy_ref');
                $table->string('currency');
                $table->date('billing_date');
                $table->date('billing_period_start')->nullable();
                $table->date('billing_period_end')->nullable();
                $table->string('billing_period_display', 20)->nullable();
                $table->string('tenancy_payment_frequency');
                $table->date('payment_due_date');
                $table->integer('grace_period')->default(0);
                $table->decimal('tenancy_billing_amount', 15, 2);
                $table->string('status')->default('Pending');
                $table->boolean('rent_invoice_dispatched')->default(false);
                $table->timestamp('rent_invoice_dispatched_at')->nullable();
                $table->string('invoice_number')->nullable();
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->date('paid_date')->nullable();
                $table->string('payment_method', 100)->nullable();
                $table->string('payment_reference')->nullable();
                $table->text('system_notes')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['payment_due_date'], 'tenant_tenancy_billings_payment_due_date_index');
                $table->index(['status'], 'tenant_tenancy_billings_status_index');
                $table->index(['tenancy_id', 'billing_date'], 'tenant_tenancy_billings_tenancy_id_billing_date_index');
            });
        }

        if (!Schema::hasTable('tenant_invoices')) {
            Schema::create('tenant_invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_number');
                $table->bigInteger('tenancy_id');
                $table->bigInteger('tenant_id');
                $table->bigInteger('billing_id')->nullable();
                $table->string('invoice_type');
                $table->string('currency', 3)->default('KES');
                $table->decimal('amount', 15, 2);
                $table->date('due_date');
                $table->date('billing_period_start')->nullable();
                $table->date('billing_period_end')->nullable();
                $table->enum('status', ['Unpaid', 'Partially Paid', 'Paid', 'Overdue', 'Cancelled'])->default('Unpaid');
                $table->json('line_items');
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->string('combined_invoice_number')->nullable();
                $table->decimal('current_billing_amount', 10, 2)->default(0);
                $table->decimal('pending_payments_amount', 10, 2)->default(0);
                $table->json('pending_payments_data')->nullable();
                $table->string('kind', 20)->default('rent');
                $table->bigInteger('related_invoice_id')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->bigInteger('voided_by')->nullable();
                $table->string('void_reason')->nullable();
                $table->unique(['invoice_number'], 'tenant_invoices_invoice_number_unique');
                $table->index(['tenancy_id', 'kind', 'status'], 'tenant_invoices_tenancy_id_kind_status_index');
            });
        }

        if (!Schema::hasTable('tenant_payments')) {
            Schema::create('tenant_payments', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('tenant_id');
                $table->bigInteger('recorded_by');
                $table->date('payment_date');
                $table->string('payment_period');
                $table->decimal('amount_paid', 10, 2);
                $table->decimal('total_amount', 10, 2);
                $table->enum('payment_method', ['cash', 'bank_transfer', 'mobile_money', 'check', 'card', 'other']);
                $table->string('transaction_reference')->nullable();
                $table->string('payment_status')->default('paid');
                $table->text('payment_notes')->nullable();
                $table->string('receipt_path')->nullable();
                $table->boolean('is_verified')->default(false);
                $table->timestamp('verified_at')->nullable();
                $table->bigInteger('verified_by')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->bigInteger('invoice_id')->nullable();
                $table->json('allocation_data')->nullable();
                $table->boolean('is_combined_payment')->default(false);
                $table->string('combined_payment_reference')->nullable();
                $table->boolean('late_fee_paid_now')->default(false);
                $table->string('entry_type', 20)->default('payment');
                $table->decimal('unallocated_amount', 12, 2)->default(0);
                $table->timestamp('confirmed_at')->nullable();
                $table->bigInteger('confirmed_by')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->bigInteger('reversed_by')->nullable();
                $table->string('reversal_reason')->nullable();
                $table->index(['created_at'], 'tenant_payments_created_at_index');
                $table->index(['invoice_id'], 'tenant_payments_invoice_id_index');
                $table->index(['payment_period'], 'tenant_payments_payment_period_index');
                $table->index(['payment_status'], 'tenant_payments_payment_status_index');
                $table->index(['tenant_id', 'payment_date'], 'tenant_payments_tenant_id_payment_date_index');
            });
        }

        if (!Schema::hasTable('tenant_invoice_payment_allocations')) {
            Schema::create('tenant_invoice_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('invoice_id');
                $table->bigInteger('payment_id');
                $table->bigInteger('tenant_id');
                $table->decimal('allocated_amount', 15, 2);
                $table->decimal('invoice_balance_before', 15, 2);
                $table->decimal('invoice_balance_after', 15, 2);
                $table->string('allocation_type')->default('payment');
                $table->json('allocation_breakdown')->nullable();
                $table->text('notes')->nullable();
                $table->bigInteger('recorded_by');
                $table->timestamp('allocation_date');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->string('source', 20)->default('payment');
                $table->string('invoice_kind', 20)->nullable();
                $table->bigInteger('agent_id')->nullable();
                $table->bigInteger('override_agent_id')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->bigInteger('commission_payout_id')->nullable();
                $table->bigInteger('override_payout_id')->nullable();
                $table->bigInteger('clawback_payout_id')->nullable();
                $table->bigInteger('override_clawback_payout_id')->nullable();
                $table->index(['agent_id', 'allocation_date'], 'tenant_invoice_payment_allocations_agent_id_allocation_date_ind');
                $table->index(['override_agent_id', 'allocation_date'], 'tenant_invoice_payment_allocations_override_agent_id_allocation');
                $table->index(['allocation_type'], 'tipa_allocation_type_idx');
                $table->index(['invoice_id', 'payment_id'], 'tipa_invoice_payment_idx');
                $table->index(['tenant_id', 'allocation_date'], 'tipa_tenant_date_idx');
            });
        }

        if (!Schema::hasTable('tenant_late_fees')) {
            Schema::create('tenant_late_fees', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('tenant_id');
                $table->bigInteger('invoice_id');
                $table->bigInteger('payment_id')->nullable();
                $table->bigInteger('recorded_by');
                $table->decimal('late_fee_amount', 15, 2);
                $table->integer('days_late');
                $table->date('due_date');
                $table->date('payment_date');
                $table->date('late_fee_date');
                $table->string('calculation_method')->nullable();
                $table->decimal('base_amount', 15, 2)->nullable();
                $table->decimal('flat_fee', 15, 2)->default(0);
                $table->decimal('daily_penalty', 15, 2)->default(0);
                $table->integer('grace_period_days')->default(0);
                $table->enum('status', ['pending', 'waived', 'paid', 'partially_paid'])->default('pending');
                $table->decimal('amount_paid', 15, 2)->default(0);
                $table->decimal('amount_waived', 15, 2)->default(0);
                $table->text('waiver_reason')->nullable();
                $table->bigInteger('waived_by')->nullable();
                $table->timestamp('waived_at')->nullable();
                $table->text('notes')->nullable();
                $table->json('calculation_details')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['due_date', 'payment_date'], 'tenant_late_fees_due_date_payment_date_index');
                $table->index(['invoice_id', 'status'], 'tenant_late_fees_invoice_id_status_index');
                $table->index(['late_fee_date'], 'tenant_late_fees_late_fee_date_index');
                $table->index(['payment_id'], 'tenant_late_fees_payment_id_index');
                $table->index(['tenant_id', 'status'], 'tenant_late_fees_tenant_id_status_index');
            });
        }

        if (!Schema::hasTable('payment_receipt_staging')) {
            Schema::create('payment_receipt_staging', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('user_id');
                $table->string('original_filename');
                $table->string('file_path');
                $table->bigInteger('file_size');
                $table->string('mime_type', 100);
                $table->boolean('matched')->default(false);
                $table->bigInteger('matched_payment_id')->nullable();
                $table->timestamp('matched_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('payment_receipt_staging');
        Schema::dropIfExists('tenant_late_fees');
        Schema::dropIfExists('tenant_invoice_payment_allocations');
        Schema::dropIfExists('tenant_payments');
        Schema::dropIfExists('tenant_invoices');
        Schema::dropIfExists('tenant_tenancy_billings');
        Schema::dropIfExists('tenant_billings');
        Schema::enableForeignKeyConstraints();
    }
};
