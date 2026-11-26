<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline: CRM: lead profiles, leads and lead tasks.
 * Generated from the schema of a running installation; each table is skipped when it already exists,
 * so existing installations can run it safely. Foreign keys are added by the last baseline migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lead_profiles')) {
            Schema::create('lead_profiles', function (Blueprint $table) {
                $table->id();
                $table->enum('lead_type', ['Individual', 'Business']);
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 20)->nullable();
                $table->string('occupation')->nullable();
                $table->date('date_of_birth')->nullable();
                $table->text('current_address')->nullable();
                $table->string('company_name')->nullable();
                $table->string('registration_number', 100)->nullable();
                $table->string('contact_person')->nullable();
                $table->string('contact_title', 100)->nullable();
                $table->string('business_email')->nullable();
                $table->string('business_phone', 20)->nullable();
                $table->string('industry', 100)->nullable();
                $table->string('company_size', 50)->nullable();
                $table->text('business_address')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['business_email'], 'lead_profiles_business_email_index');
                $table->index(['business_phone'], 'lead_profiles_business_phone_index');
                $table->index(['company_name'], 'lead_profiles_company_name_index');
                $table->index(['email'], 'lead_profiles_email_index');
                $table->index(['first_name', 'last_name'], 'lead_profiles_first_name_last_name_index');
                $table->index(['phone'], 'lead_profiles_phone_index');
            });
        }

        if (!Schema::hasTable('leads')) {
            Schema::create('leads', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('user_id');
                $table->string('status')->default('new');
                $table->enum('priority', ['hot', 'warm', 'cold'])->nullable();
                $table->string('source');
                $table->text('referral_details')->nullable();
                $table->string('property_type')->nullable();
                $table->string('purpose')->nullable();
                $table->decimal('budget_min', 15, 2)->nullable();
                $table->decimal('budget_max', 15, 2)->nullable();
                $table->string('bedrooms', 10)->nullable();
                $table->string('bathrooms', 10)->nullable();
                $table->text('location_preference')->nullable();
                $table->string('timeline', 50)->nullable();
                $table->text('special_requirements')->nullable();
                $table->enum('contact_method', ['phone', 'email', 'sms', 'whatsapp', 'any'])->default('any');
                $table->enum('contact_time', ['morning', 'afternoon', 'evening', 'weekend', 'anytime'])->default('anytime');
                $table->bigInteger('assigned_agent_id')->nullable();
                $table->timestamp('follow_up_date')->nullable();
                $table->text('notes')->nullable();
                $table->integer('lead_score')->default(0);
                $table->json('interactions')->nullable();
                $table->timestamp('last_contacted_at')->nullable();
                $table->integer('contact_attempts')->default(0);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->integer('min_sq_area')->nullable();
                $table->integer('max_sq_area')->nullable();
                $table->string('business_use')->nullable();
                $table->bigInteger('complex_id')->nullable();
                $table->string('workflow_phase')->default('qualification');
                $table->timestamp('phase_started_at')->nullable();
                $table->timestamp('conversion_started_at')->nullable();
                $table->bigInteger('converted_tenant_id')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->bigInteger('converted_by')->nullable();
                $table->bigInteger('lead_profile_id')->nullable();
                $table->timestamp('details_confirmed_at')->nullable();
                $table->bigInteger('details_confirmed_by')->nullable();
                $table->timestamp('qualification_confirmed_at')->nullable();
                $table->bigInteger('qualification_confirmed_by')->nullable();
                $table->bigInteger('created_by')->nullable();
                $table->bigInteger('converted_sale_id')->nullable();
                $table->index(['assigned_agent_id'], 'leads_assigned_agent_id_index');
                $table->index(['budget_min', 'budget_max'], 'leads_budget_min_budget_max_index');
                $table->index(['created_at'], 'leads_created_at_index');
                $table->index(['follow_up_date'], 'leads_follow_up_date_index');
                $table->index(['property_type', 'purpose'], 'leads_property_type_purpose_index');
                $table->index(['source', 'priority'], 'leads_source_priority_index');
                $table->index(['status'], 'leads_status_index');
                $table->index(['user_id'], 'leads_user_id_index');
            });
        }

        if (!Schema::hasTable('lead_task_templates')) {
            Schema::create('lead_task_templates', function (Blueprint $table) {
                $table->id();
                $table->string('task_type');
                $table->string('phase');
                $table->string('title');
                $table->text('description')->nullable();
                $table->text('instructions')->nullable();
                $table->integer('sequence_order');
                $table->integer('sla_hours');
                $table->boolean('is_required')->default(true);
                $table->boolean('blocks_next_phase')->default(false);
                $table->boolean('is_active')->default(true);
                $table->json('trigger_conditions')->nullable();
                $table->json('completion_requirements')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('lead_tasks')) {
            Schema::create('lead_tasks', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('lead_id');
                $table->bigInteger('assigned_agent_id');
                $table->bigInteger('template_id')->nullable();
                $table->string('task_type');
                $table->string('phase');
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('status')->default('pending');
                $table->integer('sequence_order')->default(0);
                $table->boolean('is_required')->default(true);
                $table->boolean('blocks_next_phase')->default(false);
                $table->timestamp('due_date')->nullable();
                $table->integer('sla_hours')->nullable();
                $table->timestamp('sla_breach_at')->nullable();
                $table->boolean('is_overdue')->default(false);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('completion_notes')->nullable();
                $table->json('completion_data')->nullable();
                $table->bigInteger('blocked_by_task_id')->nullable();
                $table->text('block_reason')->nullable();
                $table->bigInteger('created_by')->nullable();
                $table->bigInteger('completed_by')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->index(['assigned_agent_id', 'status'], 'lead_tasks_assigned_agent_id_status_index');
                $table->index(['due_date'], 'lead_tasks_due_date_index');
                $table->index(['lead_id', 'status'], 'lead_tasks_lead_id_status_index');
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('lead_tasks');
        Schema::dropIfExists('lead_task_templates');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('lead_profiles');
        Schema::enableForeignKeyConstraints();
    }
};
