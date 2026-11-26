<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline: foreign keys between the tables created by the earlier baseline migrations.
 * Keys that already exist (existing installations) are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existing = $this->foreignKeys('users');
        Schema::table('users', function (Blueprint $table) use ($existing) {
                if (!in_array('users_business_role_id_foreign', $existing, true)) {
                    $table->foreign(['business_role_id'], 'users_business_role_id_foreign')->references(['id'])->on('business_roles')->onDelete('set null');
                }
                if (!in_array('users_main_account_user_id_foreign', $existing, true)) {
                    $table->foreign(['main_account_user_id'], 'users_main_account_user_id_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('otps');
        Schema::table('otps', function (Blueprint $table) use ($existing) {
                if (!in_array('otps_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'otps_user_id_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('support_tickets');
        Schema::table('support_tickets', function (Blueprint $table) use ($existing) {
                if (!in_array('support_tickets_support_id_foreign', $existing, true)) {
                    $table->foreign(['support_id'], 'support_tickets_support_id_foreign')->references(['id'])->on('supports')->onDelete('set null');
                }
                if (!in_array('support_tickets_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'support_tickets_user_id_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
                if (!in_array('support_tickets_assigned_to_foreign', $existing, true)) {
                    $table->foreign(['assigned_to'], 'support_tickets_assigned_to_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
        });

        $existing = $this->foreignKeys('support_ticket_messages');
        Schema::table('support_ticket_messages', function (Blueprint $table) use ($existing) {
                if (!in_array('support_ticket_messages_ticket_id_foreign', $existing, true)) {
                    $table->foreign(['ticket_id'], 'support_ticket_messages_ticket_id_foreign')->references(['id'])->on('support_tickets')->onDelete('cascade');
                }
                if (!in_array('support_ticket_messages_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'support_ticket_messages_user_id_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
        });

        $existing = $this->foreignKeys('support_ticket_attachments');
        Schema::table('support_ticket_attachments', function (Blueprint $table) use ($existing) {
                if (!in_array('support_ticket_attachments_ticket_id_foreign', $existing, true)) {
                    $table->foreign(['ticket_id'], 'support_ticket_attachments_ticket_id_foreign')->references(['id'])->on('support_tickets')->onDelete('cascade');
                }
                if (!in_array('support_ticket_attachments_message_id_foreign', $existing, true)) {
                    $table->foreign(['message_id'], 'support_ticket_attachments_message_id_foreign')->references(['id'])->on('support_ticket_messages')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('model_has_permissions');
        Schema::table('model_has_permissions', function (Blueprint $table) use ($existing) {
                if (!in_array('model_has_permissions_permission_id_foreign', $existing, true)) {
                    $table->foreign(['permission_id'], 'model_has_permissions_permission_id_foreign')->references(['id'])->on('permissions')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('model_has_roles');
        Schema::table('model_has_roles', function (Blueprint $table) use ($existing) {
                if (!in_array('model_has_roles_role_id_foreign', $existing, true)) {
                    $table->foreign(['role_id'], 'model_has_roles_role_id_foreign')->references(['id'])->on('roles')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('role_has_permissions');
        Schema::table('role_has_permissions', function (Blueprint $table) use ($existing) {
                if (!in_array('role_has_permissions_permission_id_foreign', $existing, true)) {
                    $table->foreign(['permission_id'], 'role_has_permissions_permission_id_foreign')->references(['id'])->on('permissions')->onDelete('cascade');
                }
                if (!in_array('role_has_permissions_role_id_foreign', $existing, true)) {
                    $table->foreign(['role_id'], 'role_has_permissions_role_id_foreign')->references(['id'])->on('roles')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('businesses');
        Schema::table('businesses', function (Blueprint $table) use ($existing) {
                if (!in_array('businesses_suspended_by_foreign', $existing, true)) {
                    $table->foreign(['suspended_by'], 'businesses_suspended_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('businesses_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'businesses_user_id_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('business_roles');
        Schema::table('business_roles', function (Blueprint $table) use ($existing) {
                if (!in_array('business_roles_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'business_roles_user_id_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('business_payment_methods');
        Schema::table('business_payment_methods', function (Blueprint $table) use ($existing) {
                if (!in_array('business_payment_methods_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'business_payment_methods_user_id_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('complexes');
        Schema::table('complexes', function (Blueprint $table) use ($existing) {
                if (!in_array('complexes_assigned_agent_id_foreign', $existing, true)) {
                    $table->foreign(['assigned_agent_id'], 'complexes_assigned_agent_id_foreign')->references(['id'])->on('agents')->onDelete('set null');
                }
                if (!in_array('complexes_original_user_id_foreign', $existing, true)) {
                    $table->foreign(['original_user_id'], 'complexes_original_user_id_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
                if (!in_array('complexes_setup_confirmed_by_foreign', $existing, true)) {
                    $table->foreign(['setup_confirmed_by'], 'complexes_setup_confirmed_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('complexes_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'complexes_user_id_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('complex_units');
        Schema::table('complex_units', function (Blueprint $table) use ($existing) {
                if (!in_array('complex_units_complex_id_foreign', $existing, true)) {
                    $table->foreign(['complex_id'], 'complex_units_complex_id_foreign')->references(['id'])->on('complexes')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('complex_residential_units');
        Schema::table('complex_residential_units', function (Blueprint $table) use ($existing) {
                if (!in_array('complex_residential_units_assigned_agent_id_foreign', $existing, true)) {
                    $table->foreign(['assigned_agent_id'], 'complex_residential_units_assigned_agent_id_foreign')->references(['id'])->on('agents')->onDelete('set null');
                }
                if (!in_array('complex_residential_units_complex_id_foreign', $existing, true)) {
                    $table->foreign(['complex_id'], 'complex_residential_units_complex_id_foreign')->references(['id'])->on('complexes')->onDelete('cascade');
                }
                if (!in_array('complex_residential_units_complex_unit_id_foreign', $existing, true)) {
                    $table->foreign(['complex_unit_id'], 'complex_residential_units_complex_unit_id_foreign')->references(['id'])->on('complex_units')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('commercial_units');
        Schema::table('commercial_units', function (Blueprint $table) use ($existing) {
                if (!in_array('commercial_units_assigned_agent_id_foreign', $existing, true)) {
                    $table->foreign(['assigned_agent_id'], 'commercial_units_assigned_agent_id_foreign')->references(['id'])->on('agents')->onDelete('set null');
                }
                if (!in_array('commercial_units_complex_id_foreign', $existing, true)) {
                    $table->foreign(['complex_id'], 'commercial_units_complex_id_foreign')->references(['id'])->on('complexes')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('complex_amenities');
        Schema::table('complex_amenities', function (Blueprint $table) use ($existing) {
                if (!in_array('complex_amenities_complex_id_foreign', $existing, true)) {
                    $table->foreign(['complex_id'], 'complex_amenities_complex_id_foreign')->references(['id'])->on('complexes')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('complex_parking_slots');
        Schema::table('complex_parking_slots', function (Blueprint $table) use ($existing) {
                if (!in_array('complex_parking_slots_amenity_id_foreign', $existing, true)) {
                    $table->foreign(['amenity_id'], 'complex_parking_slots_amenity_id_foreign')->references(['id'])->on('complex_amenities')->onDelete('cascade');
                }
                if (!in_array('complex_parking_slots_complex_id_foreign', $existing, true)) {
                    $table->foreign(['complex_id'], 'complex_parking_slots_complex_id_foreign')->references(['id'])->on('complexes')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('complex_links');
        Schema::table('complex_links', function (Blueprint $table) use ($existing) {
                if (!in_array('complex_links_complex_id_foreign', $existing, true)) {
                    $table->foreign(['complex_id'], 'complex_links_complex_id_foreign')->references(['id'])->on('complexes')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('complex_floor_plans');
        Schema::table('complex_floor_plans', function (Blueprint $table) use ($existing) {
                if (!in_array('complex_floor_plans_complex_id_foreign', $existing, true)) {
                    $table->foreign(['complex_id'], 'complex_floor_plans_complex_id_foreign')->references(['id'])->on('complexes')->onDelete('cascade');
                }
                if (!in_array('complex_floor_plans_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'complex_floor_plans_user_id_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('complex_changes');
        Schema::table('complex_changes', function (Blueprint $table) use ($existing) {
                if (!in_array('complex_changes_complex_id_foreign', $existing, true)) {
                    $table->foreign(['complex_id'], 'complex_changes_complex_id_foreign')->references(['id'])->on('complexes')->onDelete('cascade');
                }
                if (!in_array('complex_changes_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'complex_changes_user_id_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('complex_histories');
        Schema::table('complex_histories', function (Blueprint $table) use ($existing) {
                if (!in_array('complex_histories_commercial_unit_id_foreign', $existing, true)) {
                    $table->foreign(['commercial_unit_id'], 'complex_histories_commercial_unit_id_foreign')->references(['id'])->on('commercial_units')->onDelete('cascade');
                }
                if (!in_array('complex_histories_complex_id_foreign', $existing, true)) {
                    $table->foreign(['complex_id'], 'complex_histories_complex_id_foreign')->references(['id'])->on('complexes')->onDelete('cascade');
                }
                if (!in_array('complex_histories_complex_residential_unit_id_foreign', $existing, true)) {
                    $table->foreign(['complex_residential_unit_id'], 'complex_histories_complex_residential_unit_id_foreign')->references(['id'])->on('complex_residential_units')->onDelete('cascade');
                }
                if (!in_array('complex_histories_created_by_foreign', $existing, true)) {
                    $table->foreign(['created_by'], 'complex_histories_created_by_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
                if (!in_array('complex_histories_history_category_id_foreign', $existing, true)) {
                    $table->foreign(['history_category_id'], 'complex_histories_history_category_id_foreign')->references(['id'])->on('history_categories')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('image_uploads');
        Schema::table('image_uploads', function (Blueprint $table) use ($existing) {
                if (!in_array('image_uploads_listing_id_foreign', $existing, true)) {
                    $table->foreign(['listing_id'], 'image_uploads_listing_id_foreign')->references(['id'])->on('listings')->onDelete('cascade');
                }
                if (!in_array('image_uploads_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'image_uploads_user_id_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('complex_user');
        Schema::table('complex_user', function (Blueprint $table) use ($existing) {
                if (!in_array('complex_user_complex_id_foreign', $existing, true)) {
                    $table->foreign(['complex_id'], 'complex_user_complex_id_foreign')->references(['id'])->on('complexes')->onDelete('cascade');
                }
                if (!in_array('complex_user_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'complex_user_user_id_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('assignment_history');
        Schema::table('assignment_history', function (Blueprint $table) use ($existing) {
                if (!in_array('assignment_history_agent_id_foreign', $existing, true)) {
                    $table->foreign(['agent_id'], 'assignment_history_agent_id_foreign')->references(['id'])->on('agents')->onDelete('set null');
                }
                if (!in_array('assignment_history_assigned_by_foreign', $existing, true)) {
                    $table->foreign(['assigned_by'], 'assignment_history_assigned_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('assignment_history_complex_id_foreign', $existing, true)) {
                    $table->foreign(['complex_id'], 'assignment_history_complex_id_foreign')->references(['id'])->on('complexes')->onDelete('cascade');
                }
                if (!in_array('assignment_history_ended_by_foreign', $existing, true)) {
                    $table->foreign(['ended_by'], 'assignment_history_ended_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
        });

        $existing = $this->foreignKeys('service_charges');
        Schema::table('service_charges', function (Blueprint $table) use ($existing) {
                if (!in_array('service_charges_complex_id_foreign', $existing, true)) {
                    $table->foreign(['complex_id'], 'service_charges_complex_id_foreign')->references(['id'])->on('complexes')->onDelete('cascade');
                }
                if (!in_array('service_charges_listing_id_foreign', $existing, true)) {
                    $table->foreign(['listing_id'], 'service_charges_listing_id_foreign')->references(['id'])->on('listings')->onDelete('cascade');
                }
                if (!in_array('service_charges_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'service_charges_user_id_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('listings');
        Schema::table('listings', function (Blueprint $table) use ($existing) {
                if (!in_array('listings_commercial_unit_id_foreign', $existing, true)) {
                    $table->foreign(['commercial_unit_id'], 'listings_commercial_unit_id_foreign')->references(['id'])->on('commercial_units')->onDelete('cascade');
                }
                if (!in_array('listings_complex_id_foreign', $existing, true)) {
                    $table->foreign(['complex_id'], 'listings_complex_id_foreign')->references(['id'])->on('complexes')->onDelete('cascade');
                }
                if (!in_array('listings_complex_unit_id_foreign', $existing, true)) {
                    $table->foreign(['complex_unit_id'], 'listings_complex_unit_id_foreign')->references(['id'])->on('complex_units')->onDelete('cascade');
                }
                if (!in_array('listings_original_user_id_foreign', $existing, true)) {
                    $table->foreign(['original_user_id'], 'listings_original_user_id_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
                if (!in_array('listings_reviewed_by_foreign', $existing, true)) {
                    $table->foreign(['reviewed_by'], 'listings_reviewed_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('listings_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'listings_user_id_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('leads');
        Schema::table('leads', function (Blueprint $table) use ($existing) {
                if (!in_array('leads_complex_id_foreign', $existing, true)) {
                    $table->foreign(['complex_id'], 'leads_complex_id_foreign')->references(['id'])->on('complexes')->onDelete('cascade');
                }
                if (!in_array('leads_converted_by_foreign', $existing, true)) {
                    $table->foreign(['converted_by'], 'leads_converted_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('leads_converted_tenant_id_foreign', $existing, true)) {
                    $table->foreign(['converted_tenant_id'], 'leads_converted_tenant_id_foreign')->references(['id'])->on('tenant_management')->onDelete('set null');
                }
                if (!in_array('leads_created_by_foreign', $existing, true)) {
                    $table->foreign(['created_by'], 'leads_created_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('leads_details_confirmed_by_foreign', $existing, true)) {
                    $table->foreign(['details_confirmed_by'], 'leads_details_confirmed_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('leads_lead_profile_id_foreign', $existing, true)) {
                    $table->foreign(['lead_profile_id'], 'leads_lead_profile_id_foreign')->references(['id'])->on('lead_profiles')->onDelete('cascade');
                }
                if (!in_array('leads_qualification_confirmed_by_foreign', $existing, true)) {
                    $table->foreign(['qualification_confirmed_by'], 'leads_qualification_confirmed_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('leads_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'leads_user_id_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('lead_tasks');
        Schema::table('lead_tasks', function (Blueprint $table) use ($existing) {
                if (!in_array('lead_tasks_assigned_agent_id_foreign', $existing, true)) {
                    $table->foreign(['assigned_agent_id'], 'lead_tasks_assigned_agent_id_foreign')->references(['id'])->on('agents')->onDelete('cascade');
                }
                if (!in_array('lead_tasks_blocked_by_task_id_foreign', $existing, true)) {
                    $table->foreign(['blocked_by_task_id'], 'lead_tasks_blocked_by_task_id_foreign')->references(['id'])->on('lead_tasks')->onDelete('set null');
                }
                if (!in_array('lead_tasks_completed_by_foreign', $existing, true)) {
                    $table->foreign(['completed_by'], 'lead_tasks_completed_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('lead_tasks_created_by_foreign', $existing, true)) {
                    $table->foreign(['created_by'], 'lead_tasks_created_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('lead_tasks_lead_id_foreign', $existing, true)) {
                    $table->foreign(['lead_id'], 'lead_tasks_lead_id_foreign')->references(['id'])->on('leads')->onDelete('cascade');
                }
                if (!in_array('lead_tasks_template_id_foreign', $existing, true)) {
                    $table->foreign(['template_id'], 'lead_tasks_template_id_foreign')->references(['id'])->on('lead_task_templates')->onDelete('set null');
                }
        });

        $existing = $this->foreignKeys('agent_departments');
        Schema::table('agent_departments', function (Blueprint $table) use ($existing) {
                if (!in_array('agent_departments_created_by_foreign', $existing, true)) {
                    $table->foreign(['created_by'], 'agent_departments_created_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('agent_departments_updated_by_foreign', $existing, true)) {
                    $table->foreign(['updated_by'], 'agent_departments_updated_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
        });

        $existing = $this->foreignKeys('agent_roles');
        Schema::table('agent_roles', function (Blueprint $table) use ($existing) {
                if (!in_array('agent_roles_created_by_foreign', $existing, true)) {
                    $table->foreign(['created_by'], 'agent_roles_created_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('agent_roles_department_id_foreign', $existing, true)) {
                    $table->foreign(['department_id'], 'agent_roles_department_id_foreign')->references(['id'])->on('agent_departments')->onDelete('set null');
                }
                if (!in_array('agent_roles_updated_by_foreign', $existing, true)) {
                    $table->foreign(['updated_by'], 'agent_roles_updated_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
        });

        $existing = $this->foreignKeys('agent_work_locations');
        Schema::table('agent_work_locations', function (Blueprint $table) use ($existing) {
                if (!in_array('agent_work_locations_created_by_foreign', $existing, true)) {
                    $table->foreign(['created_by'], 'agent_work_locations_created_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('agent_work_locations_updated_by_foreign', $existing, true)) {
                    $table->foreign(['updated_by'], 'agent_work_locations_updated_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
        });

        $existing = $this->foreignKeys('agents');
        Schema::table('agents', function (Blueprint $table) use ($existing) {
                if (!in_array('agents_created_by_foreign', $existing, true)) {
                    $table->foreign(['created_by'], 'agents_created_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('agents_department_id_foreign', $existing, true)) {
                    $table->foreign(['department_id'], 'agents_department_id_foreign')->references(['id'])->on('agent_departments')->onDelete('set null');
                }
                if (!in_array('agents_reporting_manager_id_foreign', $existing, true)) {
                    $table->foreign(['reporting_manager_id'], 'agents_reporting_manager_id_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('agents_reports_to_agent_id_foreign', $existing, true)) {
                    $table->foreign(['reports_to_agent_id'], 'agents_reports_to_agent_id_foreign')->references(['id'])->on('agents')->onDelete('set null');
                }
                if (!in_array('agents_role_id_foreign', $existing, true)) {
                    $table->foreign(['role_id'], 'agents_role_id_foreign')->references(['id'])->on('agent_roles')->onDelete('set null');
                }
                if (!in_array('agents_updated_by_foreign', $existing, true)) {
                    $table->foreign(['updated_by'], 'agents_updated_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('agents_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'agents_user_id_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('agents_work_location_id_foreign', $existing, true)) {
                    $table->foreign(['work_location_id'], 'agents_work_location_id_foreign')->references(['id'])->on('agent_work_locations')->onDelete('set null');
                }
        });

        $existing = $this->foreignKeys('agent_commission_tiers');
        Schema::table('agent_commission_tiers', function (Blueprint $table) use ($existing) {
                if (!in_array('agent_commission_tiers_agent_id_foreign', $existing, true)) {
                    $table->foreign(['agent_id'], 'agent_commission_tiers_agent_id_foreign')->references(['id'])->on('agents')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('commission_agreements');
        Schema::table('commission_agreements', function (Blueprint $table) use ($existing) {
                if (!in_array('commission_agreements_agent_id_foreign', $existing, true)) {
                    $table->foreign(['agent_id'], 'commission_agreements_agent_id_foreign')->references(['id'])->on('agents')->onDelete('cascade');
                }
                if (!in_array('commission_agreements_complex_id_foreign', $existing, true)) {
                    $table->foreign(['complex_id'], 'commission_agreements_complex_id_foreign')->references(['id'])->on('complexes')->onDelete('cascade');
                }
                if (!in_array('commission_agreements_created_by_foreign', $existing, true)) {
                    $table->foreign(['created_by'], 'commission_agreements_created_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('commission_agreements_ended_by_foreign', $existing, true)) {
                    $table->foreign(['ended_by'], 'commission_agreements_ended_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('commission_agreements_replaces_id_foreign', $existing, true)) {
                    $table->foreign(['replaces_id'], 'commission_agreements_replaces_id_foreign')->references(['id'])->on('commission_agreements')->onDelete('set null');
                }
        });

        $existing = $this->foreignKeys('agent_commission_payouts');
        Schema::table('agent_commission_payouts', function (Blueprint $table) use ($existing) {
                if (!in_array('agent_commission_payouts_agent_id_foreign', $existing, true)) {
                    $table->foreign(['agent_id'], 'agent_commission_payouts_agent_id_foreign')->references(['id'])->on('agents')->onDelete('cascade');
                }
                if (!in_array('agent_commission_payouts_managed_by_foreign', $existing, true)) {
                    $table->foreign(['managed_by'], 'agent_commission_payouts_managed_by_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
                if (!in_array('agent_commission_payouts_paid_by_foreign', $existing, true)) {
                    $table->foreign(['paid_by'], 'agent_commission_payouts_paid_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
        });

        $existing = $this->foreignKeys('commission_entries');
        Schema::table('commission_entries', function (Blueprint $table) use ($existing) {
                if (!in_array('commission_entries_agent_id_foreign', $existing, true)) {
                    $table->foreign(['agent_id'], 'commission_entries_agent_id_foreign')->references(['id'])->on('agents')->onDelete('set null');
                }
                if (!in_array('commission_entries_agreement_id_foreign', $existing, true)) {
                    $table->foreign(['agreement_id'], 'commission_entries_agreement_id_foreign')->references(['id'])->on('commission_agreements')->onDelete('set null');
                }
                if (!in_array('commission_entries_allocation_id_foreign', $existing, true)) {
                    $table->foreign(['allocation_id'], 'commission_entries_allocation_id_foreign')->references(['id'])->on('tenant_invoice_payment_allocations')->onDelete('set null');
                }
                if (!in_array('commission_entries_approved_by_foreign', $existing, true)) {
                    $table->foreign(['approved_by'], 'commission_entries_approved_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('commission_entries_complex_id_foreign', $existing, true)) {
                    $table->foreign(['complex_id'], 'commission_entries_complex_id_foreign')->references(['id'])->on('complexes')->onDelete('set null');
                }
                if (!in_array('commission_entries_payout_id_foreign', $existing, true)) {
                    $table->foreign(['payout_id'], 'commission_entries_payout_id_foreign')->references(['id'])->on('agent_commission_payouts')->onDelete('set null');
                }
                if (!in_array('commission_entries_reverses_entry_id_foreign', $existing, true)) {
                    $table->foreign(['reverses_entry_id'], 'commission_entries_reverses_entry_id_foreign')->references(['id'])->on('commission_entries')->onDelete('set null');
                }
                if (!in_array('commission_entries_tenancy_id_foreign', $existing, true)) {
                    $table->foreign(['tenancy_id'], 'commission_entries_tenancy_id_foreign')->references(['id'])->on('tenant_management')->onDelete('set null');
                }
        });

        $existing = $this->foreignKeys('tenant_profiles');
        Schema::table('tenant_profiles', function (Blueprint $table) use ($existing) {
                if (!in_array('tenant_profiles_created_by_foreign', $existing, true)) {
                    $table->foreign(['created_by'], 'tenant_profiles_created_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('tenant_profiles_lead_id_foreign', $existing, true)) {
                    $table->foreign(['lead_id'], 'tenant_profiles_lead_id_foreign')->references(['id'])->on('leads')->onDelete('set null');
                }
                if (!in_array('tenant_profiles_managed_by_foreign', $existing, true)) {
                    $table->foreign(['managed_by'], 'tenant_profiles_managed_by_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
                if (!in_array('tenant_profiles_updated_by_foreign', $existing, true)) {
                    $table->foreign(['updated_by'], 'tenant_profiles_updated_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
        });

        $existing = $this->foreignKeys('tenant_management');
        Schema::table('tenant_management', function (Blueprint $table) use ($existing) {
                if (!in_array('tenant_management_assigned_agent_id_foreign', $existing, true)) {
                    $table->foreign(['assigned_agent_id'], 'tenant_management_assigned_agent_id_foreign')->references(['id'])->on('agents')->onDelete('set null');
                }
                if (!in_array('tenant_management_commercial_unit_id_foreign', $existing, true)) {
                    $table->foreign(['commercial_unit_id'], 'tenant_management_commercial_unit_id_foreign')->references(['id'])->on('commercial_units')->onDelete('set null');
                }
                if (!in_array('tenant_management_complex_id_foreign', $existing, true)) {
                    $table->foreign(['complex_id'], 'tenant_management_complex_id_foreign')->references(['id'])->on('complexes')->onDelete('cascade');
                }
                if (!in_array('tenant_management_created_by_foreign', $existing, true)) {
                    $table->foreign(['created_by'], 'tenant_management_created_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('tenant_management_managed_by_foreign', $existing, true)) {
                    $table->foreign(['managed_by'], 'tenant_management_managed_by_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
                if (!in_array('tenant_management_residential_unit_id_foreign', $existing, true)) {
                    $table->foreign(['residential_unit_id'], 'tenant_management_residential_unit_id_foreign')->references(['id'])->on('complex_residential_units')->onDelete('set null');
                }
                if (!in_array('tenant_management_updated_by_foreign', $existing, true)) {
                    $table->foreign(['updated_by'], 'tenant_management_updated_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
        });

        $existing = $this->foreignKeys('tenant_leases');
        Schema::table('tenant_leases', function (Blueprint $table) use ($existing) {
                if (!in_array('tenant_leases_business_id_foreign', $existing, true)) {
                    $table->foreign(['business_id'], 'tenant_leases_business_id_foreign')->references(['id'])->on('businesses')->onDelete('cascade');
                }
                if (!in_array('tenant_leases_tenant_management_id_foreign', $existing, true)) {
                    $table->foreign(['tenant_management_id'], 'tenant_leases_tenant_management_id_foreign')->references(['id'])->on('tenant_management')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('tenant_notices');
        Schema::table('tenant_notices', function (Blueprint $table) use ($existing) {
                if (!in_array('tenant_notices_issued_by_foreign', $existing, true)) {
                    $table->foreign(['issued_by'], 'tenant_notices_issued_by_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
                if (!in_array('tenant_notices_tenant_id_foreign', $existing, true)) {
                    $table->foreign(['tenant_id'], 'tenant_notices_tenant_id_foreign')->references(['id'])->on('tenant_management')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('tenant_terminations');
        Schema::table('tenant_terminations', function (Blueprint $table) use ($existing) {
                if (!in_array('tenant_terminations_tenant_management_id_foreign', $existing, true)) {
                    $table->foreign(['tenant_management_id'], 'tenant_terminations_tenant_management_id_foreign')->references(['id'])->on('tenant_management')->onDelete('cascade');
                }
                if (!in_array('tenant_terminations_terminated_by_foreign', $existing, true)) {
                    $table->foreign(['terminated_by'], 'tenant_terminations_terminated_by_foreign')->references(['id'])->on('users');
                }
        });

        $existing = $this->foreignKeys('tenant_follow_ups');
        Schema::table('tenant_follow_ups', function (Blueprint $table) use ($existing) {
                if (!in_array('tenant_follow_ups_tenancy_id_foreign', $existing, true)) {
                    $table->foreign(['tenancy_id'], 'tenant_follow_ups_tenancy_id_foreign')->references(['id'])->on('tenant_management')->onDelete('cascade');
                }
                if (!in_array('tenant_follow_ups_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'tenant_follow_ups_user_id_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
        });

        $existing = $this->foreignKeys('tenant_legal_documents');
        Schema::table('tenant_legal_documents', function (Blueprint $table) use ($existing) {
                if (!in_array('tenant_legal_documents_legal_document_id_foreign', $existing, true)) {
                    $table->foreign(['legal_document_id'], 'tenant_legal_documents_legal_document_id_foreign')->references(['id'])->on('legals')->onDelete('cascade');
                }
                if (!in_array('tenant_legal_documents_tenant_management_id_foreign', $existing, true)) {
                    $table->foreign(['tenant_management_id'], 'tenant_legal_documents_tenant_management_id_foreign')->references(['id'])->on('tenant_management')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('tenant_billings');
        Schema::table('tenant_billings', function (Blueprint $table) use ($existing) {
                if (!in_array('tenant_billings_tenancy_billing_id_foreign', $existing, true)) {
                    $table->foreign(['tenancy_billing_id'], 'tenant_billings_tenancy_billing_id_foreign')->references(['id'])->on('tenant_tenancy_billings')->onDelete('cascade');
                }
                if (!in_array('tenant_billings_tenancy_id_foreign', $existing, true)) {
                    $table->foreign(['tenancy_id'], 'tenant_billings_tenancy_id_foreign')->references(['id'])->on('tenant_management')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('tenant_tenancy_billings');
        Schema::table('tenant_tenancy_billings', function (Blueprint $table) use ($existing) {
                if (!in_array('tenant_tenancy_billings_tenancy_id_foreign', $existing, true)) {
                    $table->foreign(['tenancy_id'], 'tenant_tenancy_billings_tenancy_id_foreign')->references(['id'])->on('tenant_management')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('tenant_invoices');
        Schema::table('tenant_invoices', function (Blueprint $table) use ($existing) {
                if (!in_array('tenant_invoices_billing_id_foreign', $existing, true)) {
                    $table->foreign(['billing_id'], 'tenant_invoices_billing_id_foreign')->references(['id'])->on('tenant_billings')->onDelete('cascade');
                }
                if (!in_array('tenant_invoices_related_invoice_id_foreign', $existing, true)) {
                    $table->foreign(['related_invoice_id'], 'tenant_invoices_related_invoice_id_foreign')->references(['id'])->on('tenant_invoices')->onDelete('set null');
                }
                if (!in_array('tenant_invoices_tenancy_id_foreign', $existing, true)) {
                    $table->foreign(['tenancy_id'], 'tenant_invoices_tenancy_id_foreign')->references(['id'])->on('tenant_management')->onDelete('cascade');
                }
                if (!in_array('tenant_invoices_tenant_id_foreign', $existing, true)) {
                    $table->foreign(['tenant_id'], 'tenant_invoices_tenant_id_foreign')->references(['id'])->on('tenant_profiles')->onDelete('cascade');
                }
                if (!in_array('tenant_invoices_voided_by_foreign', $existing, true)) {
                    $table->foreign(['voided_by'], 'tenant_invoices_voided_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
        });

        $existing = $this->foreignKeys('tenant_payments');
        Schema::table('tenant_payments', function (Blueprint $table) use ($existing) {
                if (!in_array('tenant_payments_confirmed_by_foreign', $existing, true)) {
                    $table->foreign(['confirmed_by'], 'tenant_payments_confirmed_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('tenant_payments_invoice_id_foreign', $existing, true)) {
                    $table->foreign(['invoice_id'], 'tenant_payments_invoice_id_foreign')->references(['id'])->on('tenant_invoices')->onDelete('set null');
                }
                if (!in_array('tenant_payments_recorded_by_foreign', $existing, true)) {
                    $table->foreign(['recorded_by'], 'tenant_payments_recorded_by_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
                if (!in_array('tenant_payments_reversed_by_foreign', $existing, true)) {
                    $table->foreign(['reversed_by'], 'tenant_payments_reversed_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('tenant_payments_tenant_id_foreign', $existing, true)) {
                    $table->foreign(['tenant_id'], 'tenant_payments_tenant_id_foreign')->references(['id'])->on('tenant_management')->onDelete('cascade');
                }
                if (!in_array('tenant_payments_verified_by_foreign', $existing, true)) {
                    $table->foreign(['verified_by'], 'tenant_payments_verified_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
        });

        $existing = $this->foreignKeys('tenant_invoice_payment_allocations');
        Schema::table('tenant_invoice_payment_allocations', function (Blueprint $table) use ($existing) {
                if (!in_array('tenant_invoice_payment_allocations_invoice_id_foreign', $existing, true)) {
                    $table->foreign(['invoice_id'], 'tenant_invoice_payment_allocations_invoice_id_foreign')->references(['id'])->on('tenant_invoices')->onDelete('cascade');
                }
                if (!in_array('tenant_invoice_payment_allocations_payment_id_foreign', $existing, true)) {
                    $table->foreign(['payment_id'], 'tenant_invoice_payment_allocations_payment_id_foreign')->references(['id'])->on('tenant_payments')->onDelete('cascade');
                }
                if (!in_array('tenant_invoice_payment_allocations_recorded_by_foreign', $existing, true)) {
                    $table->foreign(['recorded_by'], 'tenant_invoice_payment_allocations_recorded_by_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
                if (!in_array('tenant_invoice_payment_allocations_tenant_id_foreign', $existing, true)) {
                    $table->foreign(['tenant_id'], 'tenant_invoice_payment_allocations_tenant_id_foreign')->references(['id'])->on('tenant_management')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('tenant_late_fees');
        Schema::table('tenant_late_fees', function (Blueprint $table) use ($existing) {
                if (!in_array('tenant_late_fees_invoice_id_foreign', $existing, true)) {
                    $table->foreign(['invoice_id'], 'tenant_late_fees_invoice_id_foreign')->references(['id'])->on('tenant_invoices')->onDelete('cascade');
                }
                if (!in_array('tenant_late_fees_payment_id_foreign', $existing, true)) {
                    $table->foreign(['payment_id'], 'tenant_late_fees_payment_id_foreign')->references(['id'])->on('tenant_payments')->onDelete('set null');
                }
                if (!in_array('tenant_late_fees_recorded_by_foreign', $existing, true)) {
                    $table->foreign(['recorded_by'], 'tenant_late_fees_recorded_by_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
                if (!in_array('tenant_late_fees_tenant_id_foreign', $existing, true)) {
                    $table->foreign(['tenant_id'], 'tenant_late_fees_tenant_id_foreign')->references(['id'])->on('tenant_management')->onDelete('cascade');
                }
                if (!in_array('tenant_late_fees_waived_by_foreign', $existing, true)) {
                    $table->foreign(['waived_by'], 'tenant_late_fees_waived_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
        });

        $existing = $this->foreignKeys('payment_receipt_staging');
        Schema::table('payment_receipt_staging', function (Blueprint $table) use ($existing) {
                if (!in_array('payment_receipt_staging_matched_payment_id_foreign', $existing, true)) {
                    $table->foreign(['matched_payment_id'], 'payment_receipt_staging_matched_payment_id_foreign')->references(['id'])->on('tenant_payments')->onDelete('set null');
                }
                if (!in_array('payment_receipt_staging_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'payment_receipt_staging_user_id_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('complex_expenses');
        Schema::table('complex_expenses', function (Blueprint $table) use ($existing) {
                if (!in_array('complex_expenses_commercial_unit_id_foreign', $existing, true)) {
                    $table->foreign(['commercial_unit_id'], 'complex_expenses_commercial_unit_id_foreign')->references(['id'])->on('commercial_units')->onDelete('set null');
                }
                if (!in_array('complex_expenses_complex_id_foreign', $existing, true)) {
                    $table->foreign(['complex_id'], 'complex_expenses_complex_id_foreign')->references(['id'])->on('complexes')->onDelete('cascade');
                }
                if (!in_array('complex_expenses_residential_unit_id_foreign', $existing, true)) {
                    $table->foreign(['residential_unit_id'], 'complex_expenses_residential_unit_id_foreign')->references(['id'])->on('complex_residential_units')->onDelete('set null');
                }
                if (!in_array('complex_expenses_type_foreign', $existing, true)) {
                    $table->foreign(['type'], 'complex_expenses_type_foreign')->references(['id'])->on('expense_types')->onDelete('cascade');
                }
                if (!in_array('complex_expenses_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'complex_expenses_user_id_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('property_expense_events');
        Schema::table('property_expense_events', function (Blueprint $table) use ($existing) {
                if (!in_array('property_expense_events_expense_id_foreign', $existing, true)) {
                    $table->foreign(['expense_id'], 'property_expense_events_expense_id_foreign')->references(['id'])->on('complex_expenses')->onDelete('restrict');
                }
        });

        $existing = $this->foreignKeys('property_expense_evidence');
        Schema::table('property_expense_evidence', function (Blueprint $table) use ($existing) {
                if (!in_array('property_expense_evidence_expense_id_foreign', $existing, true)) {
                    $table->foreign(['expense_id'], 'property_expense_evidence_expense_id_foreign')->references(['id'])->on('complex_expenses')->onDelete('restrict');
                }
        });

        $existing = $this->foreignKeys('property_expense_payments');
        Schema::table('property_expense_payments', function (Blueprint $table) use ($existing) {
                if (!in_array('property_expense_payments_expense_id_foreign', $existing, true)) {
                    $table->foreign(['expense_id'], 'property_expense_payments_expense_id_foreign')->references(['id'])->on('complex_expenses')->onDelete('restrict');
                }
        });

        $existing = $this->foreignKeys('legal_folders');
        Schema::table('legal_folders', function (Blueprint $table) use ($existing) {
                if (!in_array('legal_folders_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'legal_folders_user_id_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('legals');
        Schema::table('legals', function (Blueprint $table) use ($existing) {
                if (!in_array('legals_legal_folder_id_foreign', $existing, true)) {
                    $table->foreign(['legal_folder_id'], 'legals_legal_folder_id_foreign')->references(['id'])->on('legal_folders')->onDelete('cascade');
                }
                if (!in_array('legals_legal_type_id_foreign', $existing, true)) {
                    $table->foreign(['legal_type_id'], 'legals_legal_type_id_foreign')->references(['id'])->on('legal_types')->onDelete('cascade');
                }
                if (!in_array('legals_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'legals_user_id_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('maintenance_requests');
        Schema::table('maintenance_requests', function (Blueprint $table) use ($existing) {
                if (!in_array('maintenance_requests_assigned_user_id_foreign', $existing, true)) {
                    $table->foreign(['assigned_user_id'], 'maintenance_requests_assigned_user_id_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('maintenance_requests_complex_id_foreign', $existing, true)) {
                    $table->foreign(['complex_id'], 'maintenance_requests_complex_id_foreign')->references(['id'])->on('complexes')->onDelete('cascade');
                }
                if (!in_array('maintenance_requests_logged_by_foreign', $existing, true)) {
                    $table->foreign(['logged_by'], 'maintenance_requests_logged_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('maintenance_requests_managed_by_foreign', $existing, true)) {
                    $table->foreign(['managed_by'], 'maintenance_requests_managed_by_foreign')->references(['id'])->on('users')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('maintenance_request_notes');
        Schema::table('maintenance_request_notes', function (Blueprint $table) use ($existing) {
                if (!in_array('maintenance_request_notes_maintenance_request_id_foreign', $existing, true)) {
                    $table->foreign(['maintenance_request_id'], 'maintenance_request_notes_maintenance_request_id_foreign')->references(['id'])->on('maintenance_requests')->onDelete('cascade');
                }
                if (!in_array('maintenance_request_notes_user_id_foreign', $existing, true)) {
                    $table->foreign(['user_id'], 'maintenance_request_notes_user_id_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
        });

        $existing = $this->foreignKeys('property_sales');
        Schema::table('property_sales', function (Blueprint $table) use ($existing) {
                if (!in_array('property_sales_agent_id_foreign', $existing, true)) {
                    $table->foreign(['agent_id'], 'property_sales_agent_id_foreign')->references(['id'])->on('agents')->onDelete('set null');
                }
                if (!in_array('property_sales_complex_id_foreign', $existing, true)) {
                    $table->foreign(['complex_id'], 'property_sales_complex_id_foreign')->references(['id'])->on('complexes')->onDelete('cascade');
                }
                if (!in_array('property_sales_created_by_foreign', $existing, true)) {
                    $table->foreign(['created_by'], 'property_sales_created_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('property_sales_residential_unit_id_foreign', $existing, true)) {
                    $table->foreign(['residential_unit_id'], 'property_sales_residential_unit_id_foreign')->references(['id'])->on('complex_residential_units')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('property_sale_instalments');
        Schema::table('property_sale_instalments', function (Blueprint $table) use ($existing) {
                if (!in_array('property_sale_instalments_sale_id_foreign', $existing, true)) {
                    $table->foreign(['sale_id'], 'property_sale_instalments_sale_id_foreign')->references(['id'])->on('property_sales')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('property_sale_payments');
        Schema::table('property_sale_payments', function (Blueprint $table) use ($existing) {
                if (!in_array('property_sale_payments_recorded_by_foreign', $existing, true)) {
                    $table->foreign(['recorded_by'], 'property_sale_payments_recorded_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('property_sale_payments_reversed_by_foreign', $existing, true)) {
                    $table->foreign(['reversed_by'], 'property_sale_payments_reversed_by_foreign')->references(['id'])->on('users')->onDelete('set null');
                }
                if (!in_array('property_sale_payments_sale_id_foreign', $existing, true)) {
                    $table->foreign(['sale_id'], 'property_sale_payments_sale_id_foreign')->references(['id'])->on('property_sales')->onDelete('cascade');
                }
        });

        $existing = $this->foreignKeys('property_sale_payment_allocations');
        Schema::table('property_sale_payment_allocations', function (Blueprint $table) use ($existing) {
                if (!in_array('property_sale_payment_allocations_instalment_id_foreign', $existing, true)) {
                    $table->foreign(['instalment_id'], 'property_sale_payment_allocations_instalment_id_foreign')->references(['id'])->on('property_sale_instalments')->onDelete('cascade');
                }
                if (!in_array('property_sale_payment_allocations_payment_id_foreign', $existing, true)) {
                    $table->foreign(['payment_id'], 'property_sale_payment_allocations_payment_id_foreign')->references(['id'])->on('property_sale_payments')->onDelete('cascade');
                }
        });
    }

    public function down(): void
    {
        // The tables' own down() methods drop them with their keys.
    }

    /** @return string[] names of the foreign keys a table already has */
    private function foreignKeys(string $table): array
    {
        return array_column(Schema::getForeignKeys($table), 'name');
    }
};
