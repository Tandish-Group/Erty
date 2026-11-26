<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Kinds of legal document offered when uploading to Legal. Safe to run again: rows are matched on legal_type. */
class LegalTypeSeeder extends Seeder
{
    private const ROWS = [
        ['legal_type' => 'Rental Agreement/Lease Agreement', 'description' => 'Agreements outlining terms and conditions of rental, including rent, lease duration, security deposit, and maintenance responsibilities.'],
        ['legal_type' => 'Property Addendum', 'description' => 'Additional agreements attached to standard leases, addressing specific issues such as pet policies, smoking rules, or additional regulations.'],
        ['legal_type' => 'Property Disclosure Form', 'description' => 'Documents disclosing known issues or defects related to a property, required to protect buyers and tenants.'],
        ['legal_type' => 'Lead-Based Paint Disclosure', 'description' => 'Disclosure form required by the U.S. EPA for properties built before 1978, indicating the potential presence of lead-based paint.'],
        ['legal_type' => 'Easement Agreement', 'description' => 'Agreements granting utility companies or neighboring property owners access or specific rights on the property.'],
        ['legal_type' => 'Property Management Contract', 'description' => 'Contracts outlining the scope of property management services, fees, and responsibilities.'],
        ['legal_type' => 'Landlord-Tenant Notice', 'description' => 'Notices issued by property managers and landlords to tenants, including eviction notices, lease renewal notices, or rent increase notices.'],
        ['legal_type' => 'Custom Clause', 'description' => 'Custom clauses added by property managers and landlords to rental agreements or lease contracts to address specific issues or unique circumstances.'],
    ];

    public function run(): void
    {
        foreach (self::ROWS as $row) {
            DB::table('legal_types')->updateOrInsert(['legal_type' => $row['legal_type']], $row + ['updated_at' => now(), 'created_at' => now()]);
        }
    }
}
