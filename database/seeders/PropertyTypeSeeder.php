<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Property types offered by lead and tenancy pickers (type 3 = shown everywhere, 1 = kept for older records).
 * Safe to run again: rows are matched on the name.
 */
class PropertyTypeSeeder extends Seeder
{
    private const TYPES = [
        'Apartment' => '3', 'House' => '3', 'Commercial Unit' => '3',
        'Bungalow' => '3', 'Maisonette' => '3', 'Townhouse' => '3', 'Villa' => '3', 'Cottage' => '3', 'Duplex' => '3',
        'Detached house' => '3', 'Semi-detached house' => '3', 'Mansion' => '3',
        'Penthouse' => '1',
    ];

    public function run(): void
    {
        foreach (self::TYPES as $name => $type) {
            DB::table('property_types')->updateOrInsert(['property_type' => $name], ['type' => $type, 'updated_at' => now(), 'created_at' => now()]);
        }
    }
}
