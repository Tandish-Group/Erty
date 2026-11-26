<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline: Property expenses, legal documents and maintenance.
 * Generated from the schema of a running installation; each table is skipped when it already exists,
 * so existing installations can run it safely. Foreign keys are added by the last baseline migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('expense_types')) {
            Schema::create('expense_types', function (Blueprint $table) {
                $table->id();
                $table->string('expense_type');
                $table->text('description');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('complex_expenses')) {
            Schema::create('complex_expenses', function (Blueprint $table) {
                $table->id();
                $table->text('description');
                $table->decimal('amount', 15, 2)->default(0);
                $table->string('listing_type');
                $table->string('transaction_type');
                $table->date('expense_date');
                $table->bigInteger('type')->nullable();
                $table->bigInteger('complex_id')->nullable();
                $table->bigInteger('user_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->string('currency');
                $table->enum('scope', ['complex', 'residential_unit', 'commercial_unit'])->default('complex');
                $table->bigInteger('residential_unit_id')->nullable();
                $table->bigInteger('commercial_unit_id')->nullable();
                $table->string('workflow_status')->default('unreviewed');
                $table->string('supplier')->nullable();
                $table->string('invoice_reference')->nullable();
                $table->date('due_date')->nullable();
                $table->bigInteger('reviewer_id')->nullable();
                $table->uuid('allocation_group')->nullable();
                $table->string('recurrence')->default('none');
                $table->date('next_occurrence')->nullable();
                $table->index(['allocation_group'], 'complex_expenses_allocation_group_index');
                $table->index(['workflow_status'], 'complex_expenses_workflow_status_index');
            });
        }

        if (!Schema::hasTable('property_expense_events')) {
            Schema::create('property_expense_events', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('expense_id');
                $table->bigInteger('actor_id');
                $table->string('action');
                $table->json('details');
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('property_expense_evidence')) {
            Schema::create('property_expense_evidence', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('expense_id');
                $table->bigInteger('actor_id');
                $table->string('path');
                $table->string('original_name');
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('property_expense_payments')) {
            Schema::create('property_expense_payments', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('expense_id');
                $table->bigInteger('actor_id');
                $table->decimal('amount', 15, 2);
                $table->date('paid_on');
                $table->string('method');
                $table->string('reference');
                $table->uuid('submission_id');
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['submission_id'], 'property_expense_payments_submission_id_unique');
            });
        }

        if (!Schema::hasTable('legal_types')) {
            Schema::create('legal_types', function (Blueprint $table) {
                $table->id();
                $table->string('legal_type');
                $table->text('description');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('legal_folders')) {
            Schema::create('legal_folders', function (Blueprint $table) {
                $table->id();
                $table->text('name');
                $table->text('slug');
                $table->bigInteger('user_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('legals')) {
            Schema::create('legals', function (Blueprint $table) {
                $table->id();
                $table->text('file_name');
                $table->bigInteger('legal_type_id')->nullable();
                $table->bigInteger('legal_folder_id')->nullable();
                $table->bigInteger('user_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('maintenance_requests')) {
            Schema::create('maintenance_requests', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 20);
                $table->bigInteger('managed_by');
                $table->bigInteger('complex_id');
                $table->bigInteger('residential_unit_id')->nullable();
                $table->bigInteger('commercial_unit_id')->nullable();
                $table->bigInteger('tenancy_id')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('category', 30)->default('other');
                $table->string('priority', 10)->default('normal');
                $table->string('status', 20)->default('open');
                $table->string('reported_by')->nullable();
                $table->string('reported_via', 20)->nullable();
                $table->timestamp('reported_at')->nullable();
                $table->bigInteger('logged_by')->nullable();
                $table->bigInteger('assigned_user_id')->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->text('resolution')->nullable();
                $table->decimal('cost', 12, 2)->nullable();
                $table->string('cost_borne_by', 10)->nullable();
                $table->bigInteger('expense_id')->nullable();
                $table->bigInteger('tenant_invoice_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['assigned_user_id'], 'maintenance_requests_assigned_user_id_index');
                $table->index(['complex_id', 'status'], 'maintenance_requests_complex_id_status_index');
                $table->index(['managed_by', 'status'], 'maintenance_requests_managed_by_status_index');
                $table->unique(['reference'], 'maintenance_requests_reference_unique');
                $table->index(['tenancy_id'], 'maintenance_requests_tenancy_id_index');
            });
        }

        if (!Schema::hasTable('maintenance_request_notes')) {
            Schema::create('maintenance_request_notes', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('maintenance_request_id');
                $table->bigInteger('user_id')->nullable();
                $table->string('status_from', 20)->nullable();
                $table->string('status_to', 20)->nullable();
                $table->text('note')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('maintenance_request_notes');
        Schema::dropIfExists('maintenance_requests');
        Schema::dropIfExists('legals');
        Schema::dropIfExists('legal_folders');
        Schema::dropIfExists('legal_types');
        Schema::dropIfExists('property_expense_payments');
        Schema::dropIfExists('property_expense_evidence');
        Schema::dropIfExists('property_expense_events');
        Schema::dropIfExists('complex_expenses');
        Schema::dropIfExists('expense_types');
        Schema::enableForeignKeyConstraints();
    }
};
