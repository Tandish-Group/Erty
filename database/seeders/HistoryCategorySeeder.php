<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HistoryCategory;

/** Categories of property history records. Safe to run again: rows are matched on tag. */
class HistoryCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'tag' => 'ownership',
                'name' => 'Ownership History',
                'description' => 'This section captures a timeline of who has owned the property over the years. It includes names of past owners or landlords, the type of ownership (e.g., freehold, leasehold), and official dates when ownership changed. You may also upload supporting documents like title deeds or transfer agreements.'
            ],
            [
                'tag' => 'renovation',
                'name' => 'Renovation / Remodeling History',
                'description' => 'Document any structural or aesthetic upgrades made to the property, such as kitchen remodels, repainting, flooring, or roof replacements. Include the date of work, what was done, who did it (contractor or DIY), and before-and-after images or permits if available.'
            ],
            [
                'tag' => 'structural',
                'name' => 'Structural or Building Changes',
                'description' => 'List any major changes to the layout or structure of the building. This can include extensions, demolitions, converted spaces (e.g., servant quarters to rental units), or anything that changes the square footage or configuration of the property. Include relevant approvals or architectural drawings.'
            ],
            [
                'tag' => 'legal',
                'name' => 'Legal or Regulatory Events',
                'description' => 'Record any legal matters that have affected the listing, such as land disputes, litigation, or encumbrances. This also includes licenses, zoning compliance, land use approvals, mortgage registrations, or tax disputes that have been resolved or are ongoing.'
            ],
            [
                'tag' => 'utility',
                'name' => 'Utility & Infrastructure History',
                'description' => 'Detail the history of utility installations and upgrades—such as when electricity or water was connected, installation of solar panels, boreholes, septic tanks, internet, or drainage systems. Indicate any major faults or repairs as well.'
            ],
            [
                'tag' => 'sales_rental',
                'name' => 'Past Sale and Rental Records',
                'description' => 'Show the transaction history of the property, including previous sale prices, rental amounts, and timeframes. You can include vacancy periods, agent names involved, and whether a lease agreement was signed (with optional uploads).'
            ],
            [
                'tag' => 'documentation',
                'name' => 'Documentation History',
                'description' => 'A central place to store all official property documents, both current and historical. This includes title deeds, tax clearance certificates, survey maps, mutation forms, and occupancy or completion certificates. Users may upload scanned copies or verify digital ones.'
            ],
            [
                'tag' => 'incident',
                'name' => 'Incident History',
                'description' => 'Record any incidents such as fire damage, flooding, theft, or structural accidents. You may also include claims made to insurance and the results (e.g., repairs done, value recovered). This helps buyers or renters understand property resilience and risk.'
            ],
            [
                'tag' => 'zoning',
                'name' => 'Zoning and Planning Changes',
                'description' => 'Inform users of changes in how the land or property is allowed to be used. For example, if the property was originally residential but later zoned for commercial use, or if nearby areas are scheduled for major development (like roads or malls).'
            ],
            [
                'tag' => 'media',
                'name' => 'Media Archive',
                'description' => 'A collection of all previous media related to the listing. This can include old listing photos, marketing videos, floor plans, architectural renders, and any visual content that shows how the property has evolved over time.'
            ]
        ];

        foreach ($categories as $category) {
            HistoryCategory::updateOrCreate(['tag' => $category['tag']], $category);
        }
    }
}