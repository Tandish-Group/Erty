<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Kinds of property expense offered when recording an expense. Safe to run again: rows are matched on expense_type. */
class ExpenseTypeSeeder extends Seeder
{
    private const ROWS = [
        ['expense_type' => 'Mortgage or Loan Payments', 'description' => 'If the property was financed through a mortgage or loan, regular payments are required.'],
        ['expense_type' => 'Property Taxes', 'description' => 'Property owners are responsible for paying property taxes to the local government.'],
        ['expense_type' => 'Property Insurance', 'description' => 'Covers damage to the property, including fire, theft, and natural disasters. Liability Insurance: Protects against claims by tenants or third parties for injuries or damages on the property.'],
        ['expense_type' => 'Property Management Fees', 'description' => 'If a property manager is hired, their fees are an ongoing expense.'],
        ['expense_type' => 'Maintenance and Repairs', 'description' => 'Routine Maintenance: Regular upkeep of the property, including landscaping, cleaning, and minor repairs. Major Repairs: Costs for significant repairs or renovations, such as roofing, plumbing, or electrical work.'],
        ['expense_type' => 'Utilities', 'description' => 'Water, sewer, and trash removal fees. Gas and electric bills, especially if utilities are included in the rent.'],
        ['expense_type' => 'Homeowners Association (HOA) Fees', 'description' => 'If the property is part of an HOA, fees are typically required for maintenance of common areas and amenities.'],
        ['expense_type' => 'Vacancy Costs', 'description' => 'Expenses related to periods when the property is vacant, including marketing, advertising, and property showings.'],
        ['expense_type' => 'Property Management Software', 'description' => 'Subscription fees for property management software or platforms used to streamline operations.'],
        ['expense_type' => 'Legal and Professional Fees', 'description' => 'Legal fees for lease drafting, eviction proceedings, or other legal matters. Accounting or bookkeeping fees.'],
        ['expense_type' => 'Pest Control', 'description' => 'Costs for routine pest inspections and treatments.'],
        ['expense_type' => 'Property Inspection Costs', 'description' => 'Expenses associated with routine inspections or inspections at tenant turnover.'],
        ['expense_type' => 'Marketing and Advertising', 'description' => 'Costs for advertising vacant units, creating promotional materials, and listing on rental websites.'],
        ['expense_type' => 'Tenant Screening', 'description' => 'Fees for background checks, credit checks, and tenant screening services.'],
        ['expense_type' => 'Property Depreciation', 'description' => 'Property owners may claim depreciation as a tax deduction.'],
        ['expense_type' => 'Mortgage Interest', 'description' => 'The interest portion of mortgage payments is often deductible for tax purposes.'],
        ['expense_type' => 'HOA Special Assessments', 'description' => 'Occasionally, HOAs may impose special assessments for unexpected expenses or repairs.'],
        ['expense_type' => 'Travel Expenses', 'description' => 'If the property owner or property manager must travel to the property for maintenance or inspections.'],
        ['expense_type' => 'Landscaping and Grounds Maintenance', 'description' => 'Costs for landscaping, lawn care, and snow removal, if applicable.'],
        ['expense_type' => 'Security and Alarm Systems', 'description' => 'Expenses for security systems, alarms, or monitoring services.'],
        ['expense_type' => 'Miscellaneous Expenses', 'description' => 'Bank fees for processing rent payments. Property taxes on rental income. Costs associated with eviction proceedings if necessary.'],
    ];

    public function run(): void
    {
        foreach (self::ROWS as $row) {
            DB::table('expense_types')->updateOrInsert(['expense_type' => $row['expense_type']], $row + ['updated_at' => now(), 'created_at' => now()]);
        }
    }
}
