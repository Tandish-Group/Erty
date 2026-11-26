<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The "support" sign-in role: people a platform administrator lets answer support tickets without making
 * them administrators. (Fresh installations also get it from RolesSeeder.)
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('permission.table_names.roles', 'roles');
        if (Schema::hasTable($table) && !DB::table($table)->where('name', 'support')->where('guard_name', 'web')->exists()) {
            DB::table($table)->insert(['name' => 'support', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            cache()->forget(config('permission.cache.key', 'spatie.permission.cache'));
        }
    }

    public function down(): void
    {
        // Kept: removing it would take the inbox away from support agents; restore a backup to undo.
    }
};
