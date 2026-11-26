<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline: Tenants and tenancies, leases, notices, moves and bulk import.
 * Generated from the schema of a running installation; each table is skipped when it already exists,
 * so existing installations can run it safely. Foreign keys are added by the last baseline migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenant_profiles')) {
            Schema::create('tenant_profiles', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_ref');
                $table->enum('tenant_type', ['Individual', 'Business']);
                $table->string('tenant_name')->nullable();
                $table->string('tenant_email')->nullable();
                $table->string('tenant_phone')->nullable();
                $table->string('tenant_id_number')->nullable();
                $table->date('date_of_birth')->nullable();
                $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
                $table->text('address')->nullable();
                $table->string('nationality')->nullable();
                $table->string('occupation')->nullable();
                $table->string('employer')->nullable();
                $table->decimal('monthly_income', 10, 2)->nullable();
                $table->string('business_name')->nullable();
                $table->string('business_registration')->nullable();
                $table->string('business_contact_person')->nullable();
                $table->string('business_email')->nullable();
                $table->string('business_phone')->nullable();
                $table->text('business_address')->nullable();
                $table->string('business_type')->nullable();
                $table->string('tax_id_number')->nullable();
                $table->decimal('annual_revenue', 15, 2)->nullable();
                $table->integer('years_in_business')->nullable();
                $table->string('emergency_contact_name')->nullable();
                $table->string('emergency_contact_phone')->nullable();
                $table->string('emergency_contact_relationship')->nullable();
                $table->text('emergency_contact_address')->nullable();
                $table->json('identification_documents')->nullable();
                $table->json('references')->nullable();
                $table->text('additional_notes')->nullable();
                $table->enum('profile_status', ['Active', 'Inactive', 'Blacklisted'])->default('Active');
                $table->text('blacklist_reason')->nullable();
                $table->date('blacklisted_date')->nullable();
                $table->bigInteger('managed_by');
                $table->bigInteger('created_by')->nullable();
                $table->bigInteger('updated_by')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->bigInteger('lead_id')->nullable();
                $table->index(['business_email', 'profile_status'], 'tenant_profiles_business_email_profile_status_index');
                $table->index(['business_registration'], 'tenant_profiles_business_registration_index');
                $table->index(['managed_by', 'tenant_type'], 'tenant_profiles_managed_by_tenant_type_index');
                $table->index(['tenant_email', 'profile_status'], 'tenant_profiles_tenant_email_profile_status_index');
                $table->index(['tenant_id_number'], 'tenant_profiles_tenant_id_number_index');
                $table->unique(['tenant_ref'], 'tenant_profiles_tenant_ref_unique');
                $table->index(['tenant_type', 'profile_status'], 'tenant_profiles_tenant_type_profile_status_index');
            });
        }

        if (!Schema::hasTable('tenant_management')) {
            Schema::create('tenant_management', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('tenant_profile_id');
                $table->string('tenancy_ref');
                $table->string('listing_type');
                $table->bigInteger('complex_id');
                $table->string('complex_name')->nullable();
                $table->string('furnishing', 50)->nullable();
                $table->integer('bedrooms')->nullable();
                $table->integer('bathrooms')->nullable();
                $table->decimal('sq_area', 10, 2)->nullable();
                $table->string('unit_number')->nullable();
                $table->bigInteger('commercial_unit_id')->nullable();
                $table->decimal('occupied_sq_area', 10, 2)->nullable();
                $table->decimal('rate_per_sq_ft', 10, 2)->nullable();
                $table->string('specific_unit_number')->nullable();
                $table->enum('agreement_type', ['Rental', 'Lease', 'Month-to-Month']);
                $table->decimal('monthly_payment', 10, 2);
                $table->decimal('security_deposit', 10, 2)->nullable();
                $table->date('move_in_date');
                $table->date('lease_start_date');
                $table->date('lease_end_date')->nullable();
                $table->integer('lease_duration_months')->nullable();
                $table->text('special_terms')->nullable();
                $table->date('agreement_start_date')->nullable();
                $table->date('agreement_end_date')->nullable();
                $table->integer('grace_period_days')->nullable();
                $table->decimal('late_fee_amount', 8, 2)->nullable();
                $table->decimal('penalty_amount', 8, 2)->nullable();
                $table->integer('number_of_occupants')->nullable()->default(1);
                $table->integer('has_pets')->nullable();
                $table->text('pet_details')->nullable();
                $table->text('additional_notes')->nullable();
                $table->text('general_notes')->nullable();
                $table->text('internal_notes')->nullable();
                $table->json('uploaded_documents')->nullable();
                $table->decimal('deposit_paid', 10, 2)->nullable();
                $table->date('deposit_paid_date')->nullable();
                $table->enum('payment_frequency', ['Monthly', 'Quarterly', 'Annually'])->default('Monthly');
                $table->integer('payment_due_day')->default(1);
                $table->enum('status', ['Active', 'Inactive', 'Pending', 'Terminated', 'On Notice', 'Transferred'])->default('Pending');
                $table->date('notice_given_date')->nullable();
                $table->date('expected_move_out_date')->nullable();
                $table->text('termination_reason')->nullable();
                $table->bigInteger('managed_by');
                $table->bigInteger('created_by')->nullable();
                $table->bigInteger('updated_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->string('currency')->default('KES');
                $table->integer('build_out_period_days')->nullable()->default(0);
                $table->decimal('service_charge_per_sq_ft', 10, 2)->nullable();
                $table->bigInteger('residential_unit_id')->nullable();
                $table->bigInteger('assigned_agent_id')->nullable();
                $table->string('tenancy_quarterly_payment_type', 20)->nullable();
                $table->bigInteger('previous_tenancy_id')->nullable();
                $table->date('rent_paid_up_to')->nullable();
                $table->date('billing_start_date')->nullable();
                $table->index(['agreement_end_date'], 'tenant_management_agreement_end_date_index');
                $table->index(['complex_id', 'status'], 'tenant_management_complex_id_status_index');
                $table->index(['expected_move_out_date'], 'tenant_management_expected_move_out_date_index');
                $table->index(['lease_end_date'], 'tenant_management_lease_end_date_index');
                $table->index(['managed_by', 'status'], 'tenant_management_managed_by_status_index');
                $table->index(['move_in_date'], 'tenant_management_move_in_date_index');
                $table->index(['previous_tenancy_id'], 'tenant_management_previous_tenancy_id_index');
                $table->unique(['tenancy_ref'], 'tenant_management_tenancy_ref_unique');
                $table->index(['tenant_profile_id', 'status'], 'tenant_management_tenant_profile_id_status_index');
            });
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE tenant_management ADD CONSTRAINT tenant_management_furnishing_check CHECK (((furnishing)::text = ANY (ARRAY[(\'Furnished\'::character varying)::text, (\'Partially Furnished\'::character varying)::text, (\'Unfurnished\'::character varying)::text])))');
            }
        }

        if (!Schema::hasTable('tenant_leases')) {
            Schema::create('tenant_leases', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('tenant_management_id');
                $table->bigInteger('business_id');
                $table->date('lease_start_date');
                $table->date('lease_end_date')->nullable();
                $table->integer('lease_duration_months')->nullable();
                $table->decimal('rent_amount', 15, 2);
                $table->decimal('security_deposit', 15, 2)->nullable();
                $table->string('currency', 10)->default('KES');
                $table->enum('payment_frequency', ['monthly', 'quarterly', 'semi-annually', 'annually'])->default('monthly');
                $table->integer('payment_day_of_month')->default(1);
                $table->decimal('late_fee_amount', 10, 2)->nullable();
                $table->integer('grace_period_days')->default(0);
                $table->enum('lease_type', ['fixed-term', 'month-to-month', 'periodic'])->default('fixed-term');
                $table->enum('status', ['active', 'pending', 'expired', 'terminated', 'renewed', 'transferred'])->default('pending');
                $table->boolean('auto_renewal')->default(false);
                $table->integer('renewal_notice_days')->nullable();
                $table->decimal('renewal_rent_increase_percentage', 5, 2)->nullable();
                $table->text('special_terms')->nullable();
                $table->text('terms_and_conditions')->nullable();
                $table->json('utilities_included')->nullable();
                $table->json('restrictions')->nullable();
                $table->date('termination_date')->nullable();
                $table->text('termination_reason')->nullable();
                $table->string('terminated_by')->nullable();
                $table->string('lease_document_path')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->index(['lease_end_date'], 'tenant_leases_lease_end_date_index');
                $table->index(['tenant_management_id', 'status'], 'tenant_leases_tenant_management_id_status_index');
            });
        }

        if (!Schema::hasTable('tenant_notices')) {
            Schema::create('tenant_notices', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('tenant_id');
                $table->bigInteger('issued_by');
                $table->enum('notice_type', ['lease_termination', 'rent_increase', 'lease_violation', 'property_inspection', 'maintenance_notice', 'eviction_notice', 'lease_renewal', 'other']);
                $table->date('notice_date');
                $table->date('effective_date');
                $table->integer('notice_period')->nullable();
                $table->string('notice_subject');
                $table->text('notice_details');
                $table->json('delivery_methods');
                $table->enum('delivery_status', ['pending', 'sent', 'delivered', 'failed'])->default('pending');
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->enum('tenant_response', ['pending', 'acknowledged', 'disputed', 'complied'])->default('pending');
                $table->timestamp('response_date')->nullable();
                $table->text('response_notes')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['effective_date'], 'tenant_notices_effective_date_index');
                $table->index(['notice_type'], 'tenant_notices_notice_type_index');
                $table->index(['tenant_id', 'notice_date'], 'tenant_notices_tenant_id_notice_date_index');
            });
        }

        if (!Schema::hasTable('tenant_movements')) {
            Schema::create('tenant_movements', function (Blueprint $table) {
                $table->id();
                $table->string('ref');
                $table->bigInteger('managed_by');
                $table->bigInteger('tenant_profile_id');
                $table->bigInteger('old_tenancy_id');
                $table->bigInteger('new_tenancy_id')->nullable();
                $table->bigInteger('old_complex_id');
                $table->string('old_unit_type');
                $table->bigInteger('old_unit_id')->nullable();
                $table->string('old_unit_number')->nullable();
                $table->bigInteger('new_complex_id');
                $table->string('new_unit_type');
                $table->bigInteger('new_unit_id')->nullable();
                $table->string('new_unit_number')->nullable();
                $table->string('move_type')->default('end_of_month');
                $table->date('requested_date');
                $table->date('transfer_date');
                $table->timestamp('executed_at')->nullable();
                $table->decimal('old_rent', 12, 2)->default(0);
                $table->decimal('new_rent', 12, 2)->default(0);
                $table->decimal('old_deposit', 12, 2)->default(0);
                $table->decimal('new_deposit', 12, 2)->default(0);
                $table->decimal('deposit_adjustment', 12, 2)->default(0);
                $table->string('deposit_adjustment_type')->nullable();
                $table->decimal('proration_amount', 12, 2)->default(0);
                $table->string('currency', 10)->default('USD');
                $table->decimal('arrears_amount', 12, 2)->default(0);
                $table->boolean('arrears_acknowledged')->default(false);
                $table->string('parking_action')->nullable();
                $table->bigInteger('old_parking_slot_id')->nullable();
                $table->bigInteger('new_parking_slot_id')->nullable();
                $table->json('lease_data')->nullable();
                $table->string('status')->default('pending');
                $table->bigInteger('initiated_by');
                $table->bigInteger('approved_by')->nullable();
                $table->bigInteger('cancelled_by')->nullable();
                $table->text('notes')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['managed_by'], 'tenant_movements_managed_by_index');
                $table->index(['old_tenancy_id'], 'tenant_movements_old_tenancy_id_index');
                $table->unique(['ref'], 'tenant_movements_ref_unique');
                $table->index(['status'], 'tenant_movements_status_index');
                $table->index(['tenant_profile_id'], 'tenant_movements_tenant_profile_id_index');
                $table->index(['transfer_date'], 'tenant_movements_transfer_date_index');
            });
        }

        if (!Schema::hasTable('tenant_terminations')) {
            Schema::create('tenant_terminations', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('tenant_management_id');
                $table->date('termination_date');
                $table->string('termination_reason');
                $table->text('other_reason')->nullable();
                $table->enum('notice_compliance', ['proper_notice', 'emergency_termination']);
                $table->enum('security_deposit_status', ['full_refund', 'partial_refund', 'no_refund', 'not_applicable']);
                $table->decimal('security_deposit_refund', 10, 2)->nullable();
                $table->decimal('outstanding_rent', 10, 2)->nullable();
                $table->decimal('damage_charges', 10, 2)->nullable();
                $table->decimal('net_settlement', 10, 2)->nullable();
                $table->enum('property_condition', ['good', 'fair', 'damaged']);
                $table->boolean('keys_returned')->default(false);
                $table->boolean('access_cards_returned')->default(false);
                $table->text('forwarding_address')->nullable();
                $table->text('termination_notes')->nullable();
                $table->bigInteger('terminated_by');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['tenant_management_id'], 'tenant_terminations_tenant_management_id_index');
                $table->index(['terminated_by'], 'tenant_terminations_terminated_by_index');
                $table->index(['termination_date'], 'tenant_terminations_termination_date_index');
            });
        }

        if (!Schema::hasTable('tenant_follow_ups')) {
            Schema::create('tenant_follow_ups', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('tenancy_id');
                $table->bigInteger('user_id')->nullable();
                $table->string('method', 20);
                $table->text('note');
                $table->date('contacted_on');
                $table->date('promise_date')->nullable();
                $table->decimal('promise_amount', 12, 2)->nullable();
                $table->string('promise_status', 10)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['tenancy_id', 'contacted_on'], 'tenant_follow_ups_tenancy_id_contacted_on_index');
            });
        }

        if (!Schema::hasTable('tenant_legal_documents')) {
            Schema::create('tenant_legal_documents', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('tenant_management_id');
                $table->bigInteger('legal_document_id');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('bulk_upload_batches')) {
            Schema::create('bulk_upload_batches', function (Blueprint $table) {
                $table->id();
                $table->string('batch_id');
                $table->bigInteger('user_id');
                $table->enum('upload_type', ['tenants', 'payments', 'notices']);
                $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
                $table->integer('total_records')->default(0);
                $table->integer('successful_records')->default(0);
                $table->integer('failed_records')->default(0);
                $table->text('error_message')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['batch_id'], 'bulk_upload_batches_batch_id_index');
                $table->unique(['batch_id'], 'bulk_upload_batches_batch_id_unique');
                $table->index(['status'], 'bulk_upload_batches_status_index');
                $table->index(['user_id', 'upload_type'], 'bulk_upload_batches_user_id_upload_type_index');
            });
        }

        if (!Schema::hasTable('temp_tenant_profiles')) {
            Schema::create('temp_tenant_profiles', function (Blueprint $table) {
                $table->id();
                $table->string('batch_id');
                $table->bigInteger('user_id');
                $table->string('tenant_type')->nullable();
                $table->boolean('use_existing_tenant')->default(false);
                $table->string('tenant_ref')->nullable();
                $table->string('tenant_name')->nullable();
                $table->string('tenant_email')->nullable();
                $table->string('tenant_phone')->nullable();
                $table->string('tenant_id_number')->nullable();
                $table->date('date_of_birth')->nullable();
                $table->string('gender')->nullable();
                $table->text('address')->nullable();
                $table->string('nationality')->nullable();
                $table->string('occupation')->nullable();
                $table->string('employer')->nullable();
                $table->decimal('monthly_income', 12, 2)->nullable();
                $table->string('business_name')->nullable();
                $table->string('business_registration')->nullable();
                $table->string('business_contact_person')->nullable();
                $table->string('business_email')->nullable();
                $table->string('business_phone')->nullable();
                $table->string('business_type')->nullable();
                $table->string('tax_id_number')->nullable();
                $table->decimal('annual_revenue', 15, 2)->nullable();
                $table->integer('years_in_business')->nullable();
                $table->string('emergency_contact_name')->nullable();
                $table->string('emergency_contact_phone')->nullable();
                $table->string('emergency_contact_relationship')->nullable();
                $table->text('emergency_contact_address')->nullable();
                $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');
                $table->text('error_message')->nullable();
                $table->integer('row_number');
                $table->enum('processing_status', ['pending', 'processed', 'failed'])->default('pending');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['batch_id', 'user_id'], 'temp_tenant_profiles_batch_id_user_id_index');
                $table->index(['status'], 'temp_tenant_profiles_status_index');
            });
        }

        if (!Schema::hasTable('temp_tenant_management')) {
            Schema::create('temp_tenant_management', function (Blueprint $table) {
                $table->id();
                $table->string('batch_id');
                $table->bigInteger('user_id');
                $table->bigInteger('temp_tenant_profile_id')->nullable();
                $table->string('listing_type');
                $table->string('complex_slug');
                $table->string('furnishing')->nullable();
                $table->integer('bedrooms')->nullable();
                $table->integer('bathrooms')->nullable();
                $table->decimal('sq_area', 10, 2)->nullable();
                $table->string('unit_number')->nullable();
                $table->bigInteger('commercial_unit_id')->nullable();
                $table->decimal('occupied_sq_area', 10, 2)->nullable();
                $table->decimal('rate_per_sq_ft', 8, 2)->nullable();
                $table->string('specific_unit_number')->nullable();
                $table->string('agreement_type');
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->decimal('monthly_payment', 10, 2);
                $table->decimal('security_deposit', 10, 2)->nullable();
                $table->integer('grace_period_days')->nullable();
                $table->decimal('late_fee_amount', 8, 2)->nullable();
                $table->decimal('penalty_amount', 8, 2)->nullable();
                $table->integer('number_of_occupants')->default(1);
                $table->boolean('has_pets')->default(false);
                $table->text('pet_details')->nullable();
                $table->text('general_notes')->nullable();
                $table->string('status')->default('Active');
                $table->string('payment_frequency')->default('Monthly');
                $table->integer('payment_due_day')->default(1);
                $table->enum('processing_status', ['pending', 'processed', 'failed'])->default('pending');
                $table->text('error_message')->nullable();
                $table->integer('row_number');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['batch_id', 'user_id'], 'temp_tenant_management_batch_id_user_id_index');
                $table->index(['processing_status'], 'temp_tenant_management_processing_status_index');
            });
        }

        if (!Schema::hasTable('temp_tenant_payments')) {
            Schema::create('temp_tenant_payments', function (Blueprint $table) {
                $table->id();
                $table->string('batch_id');
                $table->bigInteger('user_id');
                $table->string('tenancy_ref')->nullable();
                $table->string('tenant_profile_ref')->nullable();
                $table->string('tenant_email')->nullable();
                $table->date('payment_date');
                $table->decimal('amount_paid', 10, 2);
                $table->string('payment_method');
                $table->string('transaction_reference')->nullable();
                $table->decimal('late_fee', 8, 2)->default(0);
                $table->string('payment_status')->default('paid');
                $table->text('payment_notes')->nullable();
                $table->string('receipt_number')->nullable();
                $table->string('verified_by')->nullable();
                $table->date('verification_date')->nullable();
                $table->decimal('bank_charges', 8, 2)->default(0);
                $table->decimal('total_amount', 10, 2)->nullable();
                $table->enum('processing_status', ['pending', 'processed', 'failed'])->default('pending');
                $table->text('error_message')->nullable();
                $table->integer('row_number');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->bigInteger('tenant_id')->nullable();
                $table->string('invoice_id');
                $table->bigInteger('recorded_by')->nullable();
                $table->decimal('single_invoice_amount', 10, 2)->nullable();
                $table->decimal('lease_amount', 10, 2)->nullable();
                $table->decimal('service_charge_amount', 10, 2)->nullable();
                $table->boolean('is_combined_payment')->default(false);
                $table->string('combined_payment_reference')->nullable();
                $table->boolean('late_fee_paid_now')->default(false);
                $table->boolean('is_verified')->default(false);
                $table->timestamp('verified_at')->nullable();
                $table->string('receipt_path')->nullable();
                $table->json('allocation_data')->nullable();
                $table->index(['invoice_id'], 'temp_payments_invoice_id_index');
                $table->index(['tenancy_ref'], 'temp_payments_tenancy_ref_index');
                $table->index(['tenant_id'], 'temp_payments_tenant_id_index');
                $table->index(['batch_id', 'user_id'], 'temp_tenant_payments_batch_id_user_id_index');
                $table->index(['processing_status'], 'temp_tenant_payments_processing_status_index');
            });
        }

        if (!Schema::hasTable('temp_tenant_notices')) {
            Schema::create('temp_tenant_notices', function (Blueprint $table) {
                $table->id();
                $table->string('batch_id');
                $table->bigInteger('user_id');
                $table->string('tenancy_ref')->nullable();
                $table->string('tenant_profile_ref')->nullable();
                $table->string('tenant_email')->nullable();
                $table->string('notice_type');
                $table->date('notice_date');
                $table->date('effective_date');
                $table->string('notice_subject');
                $table->text('notice_details');
                $table->integer('notice_period')->nullable();
                $table->json('delivery_methods')->nullable();
                $table->string('priority_level')->default('medium');
                $table->boolean('response_required')->default(false);
                $table->date('response_deadline')->nullable();
                $table->boolean('follow_up_required')->default(false);
                $table->date('follow_up_date')->nullable();
                $table->integer('escalation_level')->nullable();
                $table->string('legal_reference')->nullable();
                $table->decimal('amount_involved', 10, 2)->nullable();
                $table->string('contact_person')->nullable();
                $table->string('contact_phone')->nullable();
                $table->text('internal_notes')->nullable();
                $table->enum('processing_status', ['pending', 'processed', 'failed'])->default('pending');
                $table->text('error_message')->nullable();
                $table->integer('row_number');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['batch_id', 'user_id'], 'temp_tenant_notices_batch_id_user_id_index');
                $table->index(['processing_status'], 'temp_tenant_notices_processing_status_index');
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('temp_tenant_notices');
        Schema::dropIfExists('temp_tenant_payments');
        Schema::dropIfExists('temp_tenant_management');
        Schema::dropIfExists('temp_tenant_profiles');
        Schema::dropIfExists('bulk_upload_batches');
        Schema::dropIfExists('tenant_legal_documents');
        Schema::dropIfExists('tenant_follow_ups');
        Schema::dropIfExists('tenant_terminations');
        Schema::dropIfExists('tenant_movements');
        Schema::dropIfExists('tenant_notices');
        Schema::dropIfExists('tenant_leases');
        Schema::dropIfExists('tenant_management');
        Schema::dropIfExists('tenant_profiles');
        Schema::enableForeignKeyConstraints();
    }
};
