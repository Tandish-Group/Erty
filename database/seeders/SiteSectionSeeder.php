<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Installation switches for Erty's optional modules (App\Services\Access\ModuleService). Both start on;
 * turn one off with `php artisan erty:module listings off`. Running this again keeps the current setting.
 */
class SiteSectionSeeder extends Seeder
{
    private const MODULES = [
        'listings' => ['Listings', 'One listing per unit type of a property, published through the public listings API.'],
        'sales' => ['Sales', 'Selling houses: buyer, price, deposit and instalments, receipts and sales commission.'],
    ];

    public function run(): void
    {
        foreach (self::MODULES as $key => [$label, $description]) {
            if (DB::table('site_sections')->where('key', $key)->exists()) {
                DB::table('site_sections')->where('key', $key)->update(['label' => $label, 'description' => $description, 'updated_at' => now()]);
            } else {
                DB::table('site_sections')->insert(['key' => $key, 'label' => $label, 'description' => $description, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }
}
