<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline: Businesses, their roles, payment methods and module switches.
 * Generated from the schema of a running installation; each table is skipped when it already exists,
 * so existing installations can run it safely. Foreign keys are added by the last baseline migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('businesses')) {
            Schema::create('businesses', function (Blueprint $table) {
                $table->id();
                $table->string('account_type')->nullable();
                $table->string('business_name')->nullable();
                $table->string('registration_number')->nullable();
                $table->date('date_of_incorporation')->nullable();
                $table->text('business_description')->nullable();
                $table->string('business_email')->nullable();
                $table->string('business_site')->nullable();
                $table->bigInteger('user_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->boolean('is_verified')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->boolean('is_demo_account')->nullable()->default(false);
                $table->string('document_paths')->nullable();
                $table->bigInteger('storage_used')->default(0);
                $table->json('modules')->nullable();
                $table->timestamp('starter_roles_seeded_at')->nullable();
                $table->json('starter_roles_added')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->bigInteger('suspended_by')->nullable();
                $table->unique(['business_email'], 'businesses_business_email_unique');
            });
        }

        if (!Schema::hasTable('business_roles')) {
            Schema::create('business_roles', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('business_id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('color')->default('primary');
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->bigInteger('user_id')->nullable();
                $table->json('permissions')->nullable();
                $table->string('property_scope', 20)->default('all');
                $table->string('department', 60)->nullable();
                $table->index(['business_id', 'is_active'], 'business_roles_business_id_is_active_index');
                $table->unique(['business_id', 'name'], 'business_roles_business_id_name_unique');
            });
        }

        if (!Schema::hasTable('business_payment_methods')) {
            Schema::create('business_payment_methods', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('user_id');
                $table->string('method_type');
                $table->string('label');
                $table->json('details');
                $table->boolean('is_primary')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['user_id'], 'business_payment_methods_user_id_index');
            });
        }

        if (!Schema::hasTable('site_sections')) {
            Schema::create('site_sections', function (Blueprint $table) {
                $table->id();
                $table->string('key');
                $table->string('label');
                $table->string('description')->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['key'], 'site_sections_key_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('site_sections');
        Schema::dropIfExists('business_payment_methods');
        Schema::dropIfExists('business_roles');
        Schema::dropIfExists('businesses');
        Schema::enableForeignKeyConstraints();
    }
};
