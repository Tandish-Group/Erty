<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline: Agents and their commission.
 * Generated from the schema of a running installation; each table is skipped when it already exists,
 * so existing installations can run it safely. Foreign keys are added by the last baseline migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('agent_departments')) {
            Schema::create('agent_departments', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->text('department_head')->nullable();
                $table->bigInteger('created_by')->nullable();
                $table->bigInteger('updated_by')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->index(['is_active'], 'agent_departments_is_active_index');
                $table->unique(['name'], 'agent_departments_name_unique');
            });
        }

        if (!Schema::hasTable('agent_roles')) {
            Schema::create('agent_roles', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->enum('level', ['entry', 'junior', 'mid', 'senior', 'lead', 'manager', 'director', 'executive']);
                $table->bigInteger('department_id')->nullable();
                $table->bigInteger('created_by')->nullable();
                $table->bigInteger('updated_by')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->index(['department_id'], 'agent_roles_department_id_index');
                $table->index(['is_active'], 'agent_roles_is_active_index');
                $table->index(['level'], 'agent_roles_level_index');
                $table->unique(['name', 'department_id'], 'agent_roles_name_department_id_unique');
            });
        }

        if (!Schema::hasTable('agent_work_locations')) {
            Schema::create('agent_work_locations', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('address', 500)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('state', 100)->nullable();
                $table->string('country', 100)->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->enum('type', ['office', 'branch', 'remote', 'hybrid'])->default('office');
                $table->string('phone', 20)->nullable();
                $table->string('email', 100)->nullable();
                $table->bigInteger('created_by')->nullable();
                $table->bigInteger('updated_by')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->index(['is_active'], 'agent_work_locations_is_active_index');
                $table->index(['type'], 'agent_work_locations_type_index');
            });
        }

        if (!Schema::hasTable('agents')) {
            Schema::create('agents', function (Blueprint $table) {
                $table->id();
                $table->string('first_name', 100);
                $table->string('last_name', 100);
                $table->string('email', 150);
                $table->string('phone', 20);
                $table->date('date_of_birth')->nullable();
                $table->enum('gender', ['male', 'female', 'other', 'prefer_not_to_say'])->nullable();
                $table->text('address')->nullable();
                $table->string('national_id', 50)->nullable();
                $table->string('emergency_contact', 20)->nullable();
                $table->bigInteger('department_id')->nullable();
                $table->bigInteger('role_id')->nullable();
                $table->string('employment_type', 50)->nullable();
                $table->date('start_date');
                $table->bigInteger('reporting_manager_id')->nullable();
                $table->bigInteger('work_location_id')->nullable();
                $table->integer('probation_period')->default(0);
                $table->date('contract_end_date')->nullable();
                $table->json('specializations')->nullable();
                $table->text('job_description')->nullable();
                $table->decimal('base_salary', 10, 2)->default(0);
                $table->decimal('commission_rate', 5, 2)->default(0);
                $table->enum('commission_type', ['sales_value', 'net_profit', 'flat_rate', 'tiered'])->nullable();
                $table->decimal('bonus_rate', 5, 2)->default(0);
                $table->enum('payment_frequency', ['weekly', 'bi_weekly', 'monthly', 'quarterly'])->nullable();
                $table->json('benefits')->nullable();
                $table->decimal('sales_target', 12, 2)->default(0);
                $table->string('bank_account', 100)->nullable();
                $table->text('compensation_notes')->nullable();
                $table->string('profile_photo')->nullable();
                $table->string('resume')->nullable();
                $table->text('certifications')->nullable();
                $table->integer('years_experience')->default(0);
                $table->string('previous_employer', 200)->nullable();
                $table->string('linkedin_profile')->nullable();
                $table->json('permissions')->nullable();
                $table->text('references')->nullable();
                $table->text('notes')->nullable();
                $table->enum('status', ['active', 'on_leave', 'suspended', 'terminated', 'resigned'])->default('active');
                $table->bigInteger('created_by')->nullable();
                $table->bigInteger('updated_by')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->enum('agent_type', ['agency', 'employee', 'contractor'])->default('employee');
                $table->string('company_name')->nullable();
                $table->string('agency_license')->nullable();
                $table->string('contact_person_name')->nullable();
                $table->string('contact_person_email')->nullable();
                $table->string('contact_person_phone')->nullable();
                $table->text('company_address')->nullable();
                $table->string('tax_id')->nullable();
                $table->string('business_registration_number')->nullable();
                $table->date('contract_start_date')->nullable();
                $table->string('contract_type')->nullable();
                $table->decimal('hourly_rate', 10, 2)->nullable();
                $table->decimal('project_fee', 10, 2)->nullable();
                $table->string('payment_terms')->nullable();
                $table->bigInteger('user_id')->nullable();
                $table->bigInteger('reports_to_agent_id')->nullable();
                $table->decimal('override_rate', 5, 2)->default(0);
                $table->index(['department_id'], 'agents_department_id_index');
                $table->index(['is_active'], 'agents_is_active_index');
                $table->index(['role_id'], 'agents_role_id_index');
                $table->index(['status'], 'agents_status_index');
                $table->unique(['user_id'], 'agents_user_id_unique');
                $table->index(['work_location_id'], 'agents_work_location_id_index');
                $table->unique(['created_by', 'email'], 'agents_created_by_email_unique'); // an email is unique per business
            });
        }

        if (!Schema::hasTable('agent_commission_tiers')) {
            Schema::create('agent_commission_tiers', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('agent_id');
                $table->string('label', 100);
                $table->decimal('min_value', 15, 2)->default(0);
                $table->decimal('max_value', 15, 2)->nullable();
                $table->decimal('rate', 5, 2);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('commission_agreements')) {
            Schema::create('commission_agreements', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('business_id');
                $table->string('name')->nullable();
                $table->string('scope', 20);
                $table->bigInteger('complex_id')->nullable();
                $table->bigInteger('agent_id')->nullable();
                $table->string('unit_type', 12)->nullable();
                $table->bigInteger('unit_id')->nullable();
                $table->string('entitlement', 24);
                $table->string('method', 20)->default('percent_collected');
                $table->decimal('rate', 7, 3)->default(0);
                $table->decimal('flat_amount', 14, 2)->nullable();
                $table->json('tiers')->nullable();
                $table->string('sharing', 16)->default('separate');
                $table->decimal('sub_share_pct', 6, 2)->nullable();
                $table->string('funding', 16)->default('company_fee');
                $table->json('applies_to')->nullable();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->bigInteger('replaces_id')->nullable();
                $table->string('change_reason')->nullable();
                $table->bigInteger('created_by')->nullable();
                $table->bigInteger('ended_by')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['business_id', 'entitlement', 'scope', 'effective_to'], 'commission_agreements_business_id_entitlement_scope_effective_t');
            });
        }

        if (!Schema::hasTable('agent_commission_payouts')) {
            Schema::create('agent_commission_payouts', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('agent_id')->nullable();
                $table->bigInteger('managed_by');
                $table->date('period_from');
                $table->date('period_to');
                $table->string('currency', 10)->default('KES');
                $table->decimal('rent_collected', 14, 2)->default(0);
                $table->decimal('commission', 14, 2)->default(0);
                $table->decimal('override', 14, 2)->default(0);
                $table->decimal('clawback', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->json('lines')->nullable();
                $table->string('method')->nullable();
                $table->string('reference')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('paid_at');
                $table->bigInteger('paid_by')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->string('kind', 10)->default('agent');
                $table->index(['agent_id', 'period_from'], 'agent_commission_payouts_agent_id_period_from_index');
            });
        }

        if (!Schema::hasTable('commission_entries')) {
            Schema::create('commission_entries', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('business_id');
                $table->string('kind', 12);
                $table->string('beneficiary_type', 10);
                $table->bigInteger('agent_id')->nullable();
                $table->string('entitlement', 24);
                $table->string('funding', 16)->nullable();
                $table->bigInteger('complex_id')->nullable();
                $table->string('unit_type', 12)->nullable();
                $table->bigInteger('unit_id')->nullable();
                $table->bigInteger('tenancy_id')->nullable();
                $table->bigInteger('tenant_profile_id')->nullable();
                $table->bigInteger('allocation_id')->nullable();
                $table->bigInteger('payment_id')->nullable();
                $table->bigInteger('invoice_id')->nullable();
                $table->string('rental_period', 40)->nullable();
                $table->date('collected_on')->nullable();
                $table->bigInteger('primary_agent_id')->nullable();
                $table->string('management_type', 20)->nullable();
                $table->bigInteger('agreement_id')->nullable();
                $table->string('method', 20)->nullable();
                $table->decimal('rate', 7, 3)->nullable();
                $table->decimal('basis_amount', 14, 2)->default(0);
                $table->decimal('amount', 14, 2);
                $table->string('currency', 8)->default('KES');
                $table->json('breakdown')->nullable();
                $table->date('period_from')->nullable();
                $table->date('period_to')->nullable();
                $table->string('status', 12);
                $table->bigInteger('reverses_entry_id')->nullable();
                $table->bigInteger('payout_id')->nullable();
                $table->bigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->bigInteger('sale_id')->nullable();
                $table->bigInteger('sale_payment_id')->nullable();
                $table->index(['agent_id', 'status'], 'commission_entries_agent_id_status_index');
                $table->unique(['allocation_id', 'kind', 'entitlement', 'beneficiary_type', 'agent_id'], 'commission_entries_allocation_unique');
                $table->index(['business_id', 'collected_on'], 'commission_entries_business_id_collected_on_index');
                $table->index(['business_id', 'period_from', 'period_to'], 'commission_entries_business_id_period_from_period_to_index');
                $table->index(['complex_id', 'collected_on'], 'commission_entries_complex_id_collected_on_index');
                $table->index(['sale_id'], 'commission_entries_sale_id_index');
                $table->unique(['sale_payment_id', 'kind', 'entitlement', 'agent_id'], 'commission_entries_sale_payment_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('commission_entries');
        Schema::dropIfExists('agent_commission_payouts');
        Schema::dropIfExists('commission_agreements');
        Schema::dropIfExists('agent_commission_tiers');
        Schema::dropIfExists('agents');
        Schema::dropIfExists('agent_work_locations');
        Schema::dropIfExists('agent_roles');
        Schema::dropIfExists('agent_departments');
        Schema::enableForeignKeyConstraints();
    }
};
