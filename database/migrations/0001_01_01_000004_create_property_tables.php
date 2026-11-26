<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline: Properties: complexes, unit types, units, facilities, parking, floor plans, history, assignments and service charges.
 * Generated from the schema of a running installation; each table is skipped when it already exists,
 * so existing installations can run it safely. Foreign keys are added by the last baseline migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('property_types')) {
            Schema::create('property_types', function (Blueprint $table) {
                $table->id();
                $table->string('property_type');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->string('type')->nullable();
            });
        }

        if (!Schema::hasTable('descriptions')) {
            Schema::create('descriptions', function (Blueprint $table) {
                $table->id();
                $table->string('type');
                $table->string('allocation');
                $table->text('description');
                $table->integer('section')->default(1);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('complexes')) {
            Schema::create('complexes', function (Blueprint $table) {
                $table->id();
                $table->string('ref_code');
                $table->string('title');
                $table->string('slug');
                $table->string('email');
                $table->string('mobile');
                $table->text('description');
                $table->string('type');
                $table->text('class');
                $table->string('county');
                $table->string('county_specific');
                $table->string('longitude');
                $table->string('latitude');
                $table->text('location_description');
                $table->integer('available');
                $table->integer('principal');
                $table->bigInteger('user_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->bigInteger('original_user_id')->nullable();
                $table->bigInteger('assigned_agent_id')->nullable();
                $table->string('setup_status')->default('draft');
                $table->timestamp('setup_confirmed_at')->nullable();
                $table->bigInteger('setup_confirmed_by')->nullable();
                $table->string('management_type', 20)->default('company');
                $table->index(['setup_status', 'user_id'], 'complexes_setup_status_user_id_index');
            });
        }

        if (!Schema::hasTable('complex_units')) {
            Schema::create('complex_units', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('complex_id');
                $table->enum('unit_category', ['apartment', 'house', 'commercial_unit']);
                $table->string('unit_type')->nullable();
                $table->integer('bedrooms')->nullable();
                $table->integer('bathrooms')->nullable();
                $table->integer('unit_subdivision')->nullable();
                $table->integer('unit_count');
                $table->decimal('sq_ft', 10, 2);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->smallInteger('storeys')->nullable();
                $table->decimal('plot_size', 12, 3)->nullable();
                $table->string('plot_unit', 10)->nullable();
                $table->string('offer', 8)->default('rent');
                $table->decimal('sale_price', 15, 2)->nullable();
                $table->json('features')->nullable();
                $table->index(['complex_id', 'unit_category'], 'complex_units_complex_id_unit_category_index');
            });
        }

        if (!Schema::hasTable('complex_residential_units')) {
            Schema::create('complex_residential_units', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('complex_id');
                $table->bigInteger('complex_unit_id');
                $table->string('unit_number', 50)->nullable();
                $table->integer('floor')->nullable();
                $table->string('unit_category', 50);
                $table->integer('bedrooms');
                $table->integer('bathrooms');
                $table->decimal('sq_ft', 10, 2)->nullable();
                $table->enum('status', ['available', 'occupied', 'reserved', 'maintenance', 'under_offer', 'sold'])->default('available');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->bigInteger('assigned_agent_id')->nullable();
                $table->string('house_type', 40)->nullable();
                $table->string('plot_number', 60)->nullable();
                $table->decimal('plot_size', 12, 3)->nullable();
                $table->string('plot_unit', 10)->nullable();
                $table->string('title_number', 120)->nullable();
                $table->string('offer', 8)->default('rent');
                $table->decimal('sale_price', 15, 2)->nullable();
                $table->index(['complex_id', 'status'], 'complex_residential_units_complex_id_status_index');
                $table->index(['unit_number'], 'complex_residential_units_unit_number_index');
            });
        }

        if (!Schema::hasTable('commercial_units')) {
            Schema::create('commercial_units', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('complex_id');
                $table->string('unit_number');
                $table->decimal('unit_sq_ft', 10, 2);
                $table->integer('max_subdivisions')->default(0);
                $table->decimal('min_sq_ft', 10, 2);
                $table->string('floors')->nullable();
                $table->string('custom_floors')->nullable();
                $table->decimal('monthly_rent', 10, 2)->nullable();
                $table->enum('status', ['Available', 'Occupied', 'Under Maintenance', 'Reserved']);
                $table->text('description')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->bigInteger('assigned_agent_id')->nullable();
                $table->index(['complex_id', 'status'], 'commercial_units_complex_id_status_index');
                $table->index(['unit_number'], 'commercial_units_unit_number_index');
            });
        }

        if (!Schema::hasTable('complex_amenities')) {
            Schema::create('complex_amenities', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('complex_id');
                $table->string('amenity_type');
                $table->string('parking_slots')->nullable();
                $table->text('parking_ids')->nullable();
                $table->string('garbage_frequency')->nullable();
                $table->string('internet_provider')->nullable();
                $table->integer('internet_speed')->nullable();
                $table->string('security_type')->nullable();
                $table->string('water_type')->nullable();
                $table->text('gym_description')->nullable();
                $table->integer('elevator_count')->nullable();
                $table->string('cctv_coverage')->nullable();
                $table->string('cleaning_schedule')->nullable();
                $table->string('backup_type')->nullable();
                $table->integer('conference_capacity')->nullable();
                $table->string('loading_bay_size')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->string('currency')->nullable();
                $table->decimal('annual_price', 15, 2)->nullable();
                $table->text('common_area_water_description')->nullable();
                $table->text('common_area_electricity_description')->nullable();
                $table->text('landscaping_description')->nullable();
            });
        }

        if (!Schema::hasTable('complex_parking_slots')) {
            Schema::create('complex_parking_slots', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('complex_id');
                $table->bigInteger('amenity_id');
                $table->string('parking_id');
                $table->boolean('is_occupied')->default(false);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->bigInteger('occupied_tenant_id')->nullable();
                $table->boolean('is_additional_slot')->default(false);
                $table->decimal('additional_slot_charge', 10, 2)->nullable();
                $table->unique(['parking_id'], 'complex_parking_slots_parking_id_unique');
            });
        }

        if (!Schema::hasTable('complex_links')) {
            Schema::create('complex_links', function (Blueprint $table) {
                $table->id();
                $table->string('link');
                $table->bigInteger('complex_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('complex_floor_plans')) {
            Schema::create('complex_floor_plans', function (Blueprint $table) {
                $table->id();
                $table->string('plan');
                $table->string('type')->nullable();
                $table->bigInteger('complex_id')->nullable();
                $table->bigInteger('user_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('complex_changes')) {
            Schema::create('complex_changes', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('complex_id');
                $table->string('field_name');
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->bigInteger('user_id');
                $table->string('user_ip');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('history_categories')) {
            Schema::create('history_categories', function (Blueprint $table) {
                $table->id();
                $table->string('tag');
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['tag'], 'history_categories_tag_unique');
            });
        }

        if (!Schema::hasTable('complex_histories')) {
            Schema::create('complex_histories', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('complex_id');
                $table->bigInteger('complex_residential_unit_id')->nullable();
                $table->bigInteger('commercial_unit_id')->nullable();
                $table->bigInteger('history_category_id');
                $table->string('category_tag');
                $table->json('data')->nullable();
                $table->json('files')->nullable();
                $table->text('notes')->nullable();
                $table->bigInteger('created_by');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['commercial_unit_id'], 'complex_histories_commercial_unit_id_index');
                $table->index(['complex_id', 'category_tag'], 'complex_histories_complex_id_category_tag_index');
                $table->index(['complex_residential_unit_id'], 'complex_histories_complex_residential_unit_id_index');
                $table->index(['created_by'], 'complex_histories_created_by_index');
            });
        }

        if (!Schema::hasTable('image_uploads')) {
            Schema::create('image_uploads', function (Blueprint $table) {
                $table->id();
                $table->string('image');
                $table->string('type')->nullable();
                $table->bigInteger('listing_id')->nullable();
                $table->bigInteger('user_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('complex_user')) {
            Schema::create('complex_user', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('complex_id');
                $table->bigInteger('user_id');
                $table->bigInteger('assigned_by')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['complex_id', 'user_id'], 'complex_user_complex_id_user_id_unique');
                $table->index(['user_id'], 'complex_user_user_id_index');
            });
        }

        if (!Schema::hasTable('assignment_history')) {
            Schema::create('assignment_history', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('complex_id');
                $table->string('scope', 20);
                $table->bigInteger('unit_id')->nullable();
                $table->string('assignee_type', 10);
                $table->bigInteger('agent_id')->nullable();
                $table->string('role', 12);
                $table->timestamp('starts_at');
                $table->timestamp('ends_at')->nullable();
                $table->bigInteger('assigned_by')->nullable();
                $table->bigInteger('ended_by')->nullable();
                $table->string('reason')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['agent_id', 'ends_at'], 'assignment_history_agent_id_ends_at_index');
                $table->index(['complex_id', 'scope', 'ends_at'], 'assignment_history_complex_id_scope_ends_at_index');
                $table->index(['scope', 'unit_id', 'starts_at'], 'assignment_history_scope_unit_id_starts_at_index');
            });
        }

        if (!Schema::hasTable('service_charges')) {
            Schema::create('service_charges', function (Blueprint $table) {
                $table->id();
                $table->text('name')->nullable();
                $table->integer('charge');
                $table->bigInteger('listing_id')->nullable();
                $table->bigInteger('amenity_id')->nullable();
                $table->string('amenity_type')->nullable();
                $table->bigInteger('complex_id')->nullable();
                $table->bigInteger('user_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('service_charges');
        Schema::dropIfExists('assignment_history');
        Schema::dropIfExists('complex_user');
        Schema::dropIfExists('image_uploads');
        Schema::dropIfExists('complex_histories');
        Schema::dropIfExists('history_categories');
        Schema::dropIfExists('complex_changes');
        Schema::dropIfExists('complex_floor_plans');
        Schema::dropIfExists('complex_links');
        Schema::dropIfExists('complex_parking_slots');
        Schema::dropIfExists('complex_amenities');
        Schema::dropIfExists('commercial_units');
        Schema::dropIfExists('complex_residential_units');
        Schema::dropIfExists('complex_units');
        Schema::dropIfExists('complexes');
        Schema::dropIfExists('descriptions');
        Schema::dropIfExists('property_types');
        Schema::enableForeignKeyConstraints();
    }
};
