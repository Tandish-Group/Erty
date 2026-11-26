<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Reference data every installation needs. Safe to run again at any time (each seeder matches existing rows).
 *
 *   php artisan db:seed
 *
 * The first administrator and business are created by `php artisan erty:install`; demo data by
 * `php artisan db:seed --class=DemoSeeder` (local and testing environments only).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            SiteSectionSeeder::class,
            PropertyTypeSeeder::class,
            HistoryCategorySeeder::class,
            LeadTaskTemplateSeeder::class,
            ExpenseTypeSeeder::class,
            LegalTypeSeeder::class,
            SupportTopicSeeder::class,
            DescriptionSeeder::class,
        ]);
    }
}
