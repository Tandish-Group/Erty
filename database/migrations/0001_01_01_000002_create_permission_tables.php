<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline: Roles and permissions (spatie/laravel-permission).
 * Generated from the schema of a running installation; each table is skipped when it already exists,
 * so existing installations can run it safely. Foreign keys are added by the last baseline migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['name', 'guard_name'], 'permissions_name_guard_name_unique');
            });
        }

        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
            });
        }

        if (!Schema::hasTable('model_has_permissions')) {
            Schema::create('model_has_permissions', function (Blueprint $table) {
                $table->bigInteger('permission_id');
                $table->string('model_type');
                $table->bigInteger('model_id');
                $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_pkey');
                $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            });
        }

        if (!Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $table) {
                $table->bigInteger('role_id');
                $table->string('model_type');
                $table->bigInteger('model_id');
                $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_pkey');
                $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            });
        }

        if (!Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function (Blueprint $table) {
                $table->bigInteger('permission_id');
                $table->bigInteger('role_id');
                $table->primary(['permission_id', 'role_id'], 'role_has_permissions_pkey');
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::enableForeignKeyConstraints();
    }
};
