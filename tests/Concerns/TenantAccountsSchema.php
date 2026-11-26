<?php

namespace Tests\Concerns;

use App\Models\TenantManagement;
use App\Models\TenantMovement;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** An in-memory schema for tenant accounts, charges, transfers and commission, with small factories. */
trait TenantAccountsSchema
{
    protected function tenancy(array $overrides = []): TenantManagement
    {
        return TenantManagement::create(array_merge([
            'tenancy_ref' => 'TM' . strtoupper(\Illuminate\Support\Str::random(8)),
            'tenant_profile_id' => 1,
            'complex_id' => 1,
            'complex_name' => 'Riverside',
            'listing_type' => 'Residential Unit',
            'residential_unit_id' => 1,
            'unit_number' => 'A-101',
            'agreement_type' => 'Rent',
            'monthly_payment' => 35000,
            'security_deposit' => 35000,
            'currency' => 'KES',
            'move_in_date' => '2026-11-15',
            'lease_start_date' => '2026-11-15',
            'payment_frequency' => 'Monthly',
            'grace_period_days' => 0,
            'late_fee_amount' => 0,
            'status' => 'Active',
            'managed_by' => 10,
        ], $overrides));
    }

    protected function pay(float $amount, array $extra = []): array
    {
        return array_merge(['amount' => $amount, 'payment_date' => Carbon::now()->toDateString(), 'payment_method' => 'mobile_money'], $extra);
    }

    protected function movement(TenantManagement $t, array $data): TenantMovement
    {
        return TenantMovement::create(array_merge([
            'ref' => TenantMovement::generateRef(),
            'managed_by' => 10,
            'tenant_profile_id' => $t->tenant_profile_id,
            'old_tenancy_id' => $t->id,
            'old_complex_id' => $t->complex_id,
            'old_unit_type' => 'residential',
            'old_unit_id' => $t->residential_unit_id,
            'old_unit_number' => $t->unit_number,
            'new_complex_id' => 1,
            'new_unit_type' => 'residential',
            'new_unit_id' => 2,
            'new_unit_number' => 'A-102',
            'requested_date' => Carbon::now()->toDateString(),
            'old_rent' => $t->monthly_payment,
            'old_deposit' => $t->security_deposit,
            'currency' => 'KES',
            'parking_action' => 'none',
            'lease_data' => [],
            'status' => 'pending',
            'initiated_by' => 10,
        ], $data));
    }

    protected function schema(): void
    {
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('first_name')->nullable(); $t->string('last_name')->nullable(); $t->string('email')->nullable(); $t->boolean('is_sub_account')->default(false); $t->integer('main_account_user_id')->nullable(); $t->integer('business_role_id')->nullable(); $t->json('business_permissions')->nullable(); $t->timestamps(); });
        Schema::create('businesses', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->string('business_name'); $t->timestamps(); });
        Schema::create('complexes', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->string('title'); $t->string('slug')->nullable(); $t->integer('assigned_agent_id')->nullable(); $t->timestamps(); });
        Schema::create('complex_user', function (Blueprint $t) { $t->id(); $t->integer('complex_id'); $t->integer('user_id'); $t->timestamps(); });
        Schema::create('agents', function (Blueprint $t) {
            $t->id(); $t->integer('user_id')->nullable(); $t->integer('created_by'); $t->string('first_name'); $t->string('last_name'); $t->string('email')->nullable();
            $t->string('phone')->default('1'); $t->string('agent_type')->default('employee'); $t->string('commission_type')->nullable(); $t->decimal('commission_rate')->default(0);
            $t->decimal('base_salary')->default(0); $t->decimal('sales_target')->default(0); $t->string('status')->default('active'); $t->boolean('is_active')->default(true);
            $t->integer('reports_to_agent_id')->nullable(); $t->decimal('override_rate')->default(0); $t->integer('updated_by')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('agent_commission_tiers', function (Blueprint $t) { $t->id(); $t->integer('agent_id'); $t->string('label')->nullable(); $t->decimal('min_value'); $t->decimal('max_value')->nullable(); $t->decimal('rate'); $t->timestamps(); });
        Schema::create('agent_commission_payouts', function (Blueprint $t) {
            $t->id(); $t->integer('agent_id'); $t->integer('managed_by'); $t->date('period_from'); $t->date('period_to'); $t->string('currency')->default('KES');
            foreach (['rent_collected', 'commission', 'override', 'clawback', 'total'] as $c) { $t->decimal($c, 14, 2)->default(0); }
            $t->json('lines')->nullable(); $t->string('method')->nullable(); $t->string('reference')->nullable(); $t->text('notes')->nullable(); $t->timestamp('paid_at'); $t->integer('paid_by')->nullable(); $t->timestamps();
        });
        Schema::create('complex_expenses', function (Blueprint $t) {
            $t->id(); $t->integer('complex_id'); $t->decimal('amount'); $t->date('expense_date'); $t->string('workflow_status')->nullable(); $t->string('allocation_group')->nullable();
            $t->string('scope')->nullable(); $t->integer('residential_unit_id')->nullable(); $t->integer('commercial_unit_id')->nullable(); $t->integer('type')->nullable(); $t->text('description')->nullable();
            $t->string('currency')->nullable(); $t->string('listing_type')->nullable(); $t->string('transaction_type')->nullable(); $t->integer('user_id')->nullable(); $t->timestamps();
        });
        Schema::create('expense_types', function (Blueprint $t) { $t->id(); $t->string('expense_type'); $t->timestamps(); });
        Schema::create('property_expense_events', function (Blueprint $t) { $t->id(); $t->integer('expense_id'); $t->integer('actor_id')->nullable(); $t->string('action'); $t->json('details')->nullable(); $t->timestamp('created_at')->nullable(); });
        Schema::create('maintenance_requests', function (Blueprint $t) {
            $t->id(); $t->string('reference')->unique(); $t->integer('managed_by'); $t->integer('complex_id'); $t->integer('residential_unit_id')->nullable(); $t->integer('commercial_unit_id')->nullable();
            $t->integer('tenancy_id')->nullable(); $t->string('title'); $t->text('description')->nullable(); $t->string('category')->default('other'); $t->string('priority')->default('normal');
            $t->string('status')->default('open'); $t->string('reported_by')->nullable(); $t->string('reported_via')->nullable(); $t->timestamp('reported_at')->nullable(); $t->integer('logged_by')->nullable();
            $t->integer('assigned_user_id')->nullable(); $t->timestamp('assigned_at')->nullable(); $t->timestamp('started_at')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamp('closed_at')->nullable();
            $t->text('resolution')->nullable(); $t->decimal('cost', 12, 2)->nullable(); $t->string('cost_borne_by')->nullable(); $t->integer('expense_id')->nullable(); $t->integer('tenant_invoice_id')->nullable(); $t->timestamps();
        });
        Schema::create('maintenance_request_notes', function (Blueprint $t) { $t->id(); $t->integer('maintenance_request_id'); $t->integer('user_id')->nullable(); $t->string('status_from')->nullable(); $t->string('status_to')->nullable(); $t->text('note')->nullable(); $t->timestamps(); });
        Schema::create('tenant_follow_ups', function (Blueprint $t) { $t->id(); $t->integer('tenancy_id'); $t->integer('user_id')->nullable(); $t->string('method'); $t->text('note'); $t->date('contacted_on'); $t->date('promise_date')->nullable(); $t->decimal('promise_amount', 12, 2)->nullable(); $t->string('promise_status')->nullable(); $t->timestamps(); });
        Schema::create('complex_residential_units', function (Blueprint $t) { $t->id(); $t->integer('complex_id'); $t->string('unit_number'); $t->string('status')->default('available'); $t->integer('bedrooms')->nullable(); $t->integer('bathrooms')->nullable(); $t->double('sq_area')->nullable(); $t->integer('assigned_agent_id')->nullable(); $t->timestamps(); });
        Schema::create('commercial_units', function (Blueprint $t) { $t->id(); $t->integer('complex_id'); $t->string('unit_number'); $t->double('unit_sq_ft'); $t->double('min_sq_ft')->default(0); $t->integer('max_subdivisions')->default(1); $t->string('status')->default('Available'); $t->integer('assigned_agent_id')->nullable(); $t->timestamps(); });
        Schema::create('complex_parking_slots', function (Blueprint $t) { $t->id(); $t->integer('complex_id'); $t->boolean('is_occupied')->default(false); $t->integer('occupied_tenant_id')->nullable(); $t->timestamps(); });
        Schema::create('tenant_profiles', function (Blueprint $t) { $t->id(); $t->string('tenant_ref')->nullable(); $t->string('tenant_type')->default('Individual'); $t->string('tenant_name')->nullable(); $t->string('business_name')->nullable(); $t->string('tenant_email')->nullable(); $t->string('business_email')->nullable(); $t->integer('managed_by')->nullable(); $t->integer('created_by')->nullable(); $t->timestamps(); });
        Schema::create('tenant_management', function (Blueprint $t) {
            $t->id(); $t->string('tenancy_ref')->nullable(); $t->integer('previous_tenancy_id')->nullable(); $t->integer('tenant_profile_id')->nullable();
            $t->integer('complex_id')->nullable(); $t->string('complex_name')->nullable(); $t->string('listing_type')->nullable(); $t->string('furnishing')->nullable();
            $t->integer('residential_unit_id')->nullable(); $t->integer('commercial_unit_id')->nullable(); $t->string('unit_number')->nullable(); $t->string('specific_unit_number')->nullable();
            $t->integer('bedrooms')->nullable(); $t->integer('bathrooms')->nullable(); $t->double('sq_area')->nullable(); $t->double('occupied_sq_area')->nullable();
            $t->decimal('rate_per_sq_ft')->nullable(); $t->decimal('service_charge_per_sq_ft')->nullable(); $t->string('status')->default('Active'); $t->integer('assigned_agent_id')->nullable();
            $t->integer('managed_by'); $t->integer('created_by')->nullable(); $t->integer('updated_by')->nullable(); $t->string('agreement_type')->nullable();
            $t->decimal('monthly_payment', 12, 2)->default(0); $t->decimal('security_deposit', 12, 2)->nullable(); $t->string('currency')->default('KES');
            $t->date('move_in_date')->nullable(); $t->date('lease_start_date')->nullable(); $t->date('lease_end_date')->nullable(); $t->integer('lease_duration_months')->nullable();
            $t->date('agreement_start_date')->nullable(); $t->date('agreement_end_date')->nullable(); $t->text('special_terms')->nullable(); $t->integer('build_out_period_days')->nullable();
            $t->date('billing_start_date')->nullable(); $t->date('rent_paid_up_to')->nullable(); $t->string('payment_frequency')->nullable(); $t->integer('payment_due_day')->nullable();
            $t->integer('grace_period_days')->nullable(); $t->decimal('late_fee_amount')->nullable(); $t->decimal('penalty_amount')->nullable(); $t->string('tenancy_quarterly_payment_type')->nullable();
            $t->integer('number_of_occupants')->nullable(); $t->boolean('has_pets')->default(false); $t->text('pet_details')->nullable();
            $t->decimal('deposit_paid', 12, 2)->nullable(); $t->date('deposit_paid_date')->nullable(); $t->date('expected_move_out_date')->nullable(); $t->timestamps();
        });
        Schema::create('tenant_leases', function (Blueprint $t) {
            $t->id(); $t->integer('tenant_management_id'); $t->integer('business_id')->nullable(); $t->date('lease_start_date')->nullable(); $t->date('lease_end_date')->nullable();
            $t->integer('lease_duration_months')->nullable(); $t->decimal('rent_amount', 12, 2)->nullable(); $t->decimal('security_deposit', 12, 2)->nullable(); $t->string('currency')->nullable();
            $t->string('payment_frequency')->nullable(); $t->integer('payment_day_of_month')->nullable(); $t->decimal('late_fee_amount')->nullable(); $t->integer('grace_period_days')->nullable();
            $t->string('lease_type')->nullable(); $t->string('status')->nullable(); $t->text('special_terms')->nullable(); $t->date('termination_date')->nullable(); $t->string('termination_reason')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('tenant_movements', function (Blueprint $t) {
            $t->id(); $t->string('ref'); $t->integer('managed_by'); $t->integer('tenant_profile_id')->nullable(); $t->integer('old_tenancy_id'); $t->integer('new_tenancy_id')->nullable();
            $t->integer('old_complex_id')->nullable(); $t->string('old_unit_type')->nullable(); $t->integer('old_unit_id')->nullable(); $t->string('old_unit_number')->nullable();
            $t->integer('new_complex_id')->nullable(); $t->string('new_unit_type')->nullable(); $t->integer('new_unit_id')->nullable(); $t->string('new_unit_number')->nullable();
            $t->string('move_type')->nullable(); $t->date('requested_date')->nullable(); $t->date('transfer_date')->nullable(); $t->timestamp('executed_at')->nullable();
            $t->decimal('old_rent', 12, 2)->nullable(); $t->decimal('new_rent', 12, 2)->nullable(); $t->decimal('old_deposit', 12, 2)->nullable(); $t->decimal('new_deposit', 12, 2)->nullable();
            $t->decimal('deposit_adjustment', 12, 2)->nullable(); $t->string('deposit_adjustment_type')->nullable(); $t->decimal('proration_amount', 12, 2)->nullable(); $t->string('currency')->nullable();
            $t->decimal('arrears_amount', 12, 2)->nullable(); $t->boolean('arrears_acknowledged')->default(false); $t->string('parking_action')->nullable(); $t->integer('old_parking_slot_id')->nullable();
            $t->integer('new_parking_slot_id')->nullable(); $t->json('lease_data')->nullable(); $t->string('status'); $t->integer('initiated_by')->nullable(); $t->integer('approved_by')->nullable();
            $t->integer('cancelled_by')->nullable(); $t->text('notes')->nullable(); $t->text('cancellation_reason')->nullable(); $t->timestamps();
        });
        Schema::create('tenant_tenancy_billings', function (Blueprint $t) {
            $t->id(); $t->integer('tenancy_id'); $t->string('tenancy_ref'); $t->string('currency'); $t->date('billing_date'); $t->date('billing_period_start')->nullable(); $t->date('billing_period_end')->nullable();
            $t->string('billing_period_display', 20)->nullable(); $t->string('tenancy_payment_frequency'); $t->date('payment_due_date'); $t->integer('grace_period'); $t->decimal('tenancy_billing_amount', 12, 2);
            $t->string('status'); $t->boolean('rent_invoice_dispatched')->default(false); $t->timestamp('rent_invoice_dispatched_at')->nullable(); $t->string('invoice_number')->nullable();
            $t->decimal('paid_amount', 12, 2)->default(0); $t->date('paid_date')->nullable(); $t->string('payment_method')->nullable(); $t->string('payment_reference')->nullable(); $t->text('system_notes')->nullable(); $t->timestamps();
        });
        Schema::create('tenant_billings', function (Blueprint $t) {
            $t->id(); $t->integer('tenancy_id'); $t->string('tenancy_ref'); $t->string('currency'); $t->date('billing_date'); $t->string('billing_type'); $t->integer('tenancy_billing_id');
            $t->decimal('total_amount', 12, 2); $t->string('status'); $t->text('system_notes')->nullable(); $t->timestamps();
        });
        Schema::create('tenant_invoices', function (Blueprint $t) {
            $t->id(); $t->string('invoice_number'); $t->integer('tenancy_id'); $t->integer('tenant_id'); $t->integer('billing_id')->nullable(); $t->string('invoice_type');
            $t->string('kind', 20)->default('rent'); $t->integer('related_invoice_id')->nullable(); $t->string('currency', 3); $t->decimal('amount', 12, 2);
            $t->decimal('current_billing_amount', 12, 2)->default(0); $t->decimal('pending_payments_amount', 12, 2)->default(0); $t->json('pending_payments_data')->nullable();
            $t->decimal('paid_amount', 12, 2)->default(0); $t->date('due_date'); $t->date('billing_period_start')->nullable(); $t->date('billing_period_end')->nullable();
            $t->string('status')->default('Unpaid'); $t->json('line_items'); $t->timestamp('paid_at')->nullable(); $t->string('combined_invoice_number')->nullable();
            $t->timestamp('voided_at')->nullable(); $t->integer('voided_by')->nullable(); $t->string('void_reason')->nullable(); $t->timestamps();
        });
        Schema::create('tenant_payments', function (Blueprint $t) {
            $t->id(); $t->integer('tenant_id'); $t->string('entry_type', 20)->default('payment'); $t->integer('invoice_id')->nullable(); $t->integer('recorded_by')->nullable();
            $t->date('payment_date'); $t->string('payment_period')->nullable(); $t->decimal('amount_paid', 12, 2); $t->decimal('total_amount', 12, 2)->nullable(); $t->decimal('unallocated_amount', 12, 2)->default(0);
            $t->string('payment_method'); $t->string('transaction_reference')->nullable(); $t->string('payment_status'); $t->text('payment_notes')->nullable(); $t->string('receipt_path')->nullable();
            $t->boolean('is_verified')->default(false); $t->timestamp('verified_at')->nullable(); $t->integer('verified_by')->nullable(); $t->json('allocation_data')->nullable();
            $t->boolean('is_combined_payment')->default(false); $t->string('combined_payment_reference')->nullable(); $t->boolean('late_fee_paid_now')->default(false);
            $t->timestamp('confirmed_at')->nullable(); $t->integer('confirmed_by')->nullable(); $t->timestamp('reversed_at')->nullable(); $t->integer('reversed_by')->nullable(); $t->string('reversal_reason')->nullable(); $t->timestamps();
        });
        Schema::create('tenant_invoice_payment_allocations', function (Blueprint $t) {
            $t->id(); $t->integer('invoice_id'); $t->integer('payment_id')->nullable(); $t->integer('tenant_id'); $t->decimal('allocated_amount', 12, 2);
            $t->decimal('invoice_balance_before', 12, 2)->nullable(); $t->decimal('invoice_balance_after', 12, 2)->nullable(); $t->string('allocation_type')->nullable();
            $t->string('source', 20)->default('payment'); $t->string('invoice_kind', 20)->nullable(); $t->integer('agent_id')->nullable(); $t->integer('override_agent_id')->nullable();
            $t->json('allocation_breakdown')->nullable(); $t->text('notes')->nullable(); $t->integer('recorded_by')->nullable(); $t->date('allocation_date')->nullable(); $t->timestamp('reversed_at')->nullable();
            $t->integer('commission_payout_id')->nullable(); $t->integer('override_payout_id')->nullable(); $t->integer('clawback_payout_id')->nullable(); $t->integer('override_clawback_payout_id')->nullable(); $t->timestamps();
        });
    }
}
