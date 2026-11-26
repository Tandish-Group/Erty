<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The sign-in roles: "business" for everyone who works in a business (the owner and their team), "admin" for
 * platform administrators, and "support" for people who answer support tickets without being administrators.
 * What a team member may do in the business is set by business roles, not here.
 */
class RolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['business', 'admin', 'support'] as $name) {
            Role::findOrCreate($name, 'web');
        }
    }
}
