<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Topics offered on a support ticket. Safe to run again: rows are matched on the topic. */
class SupportTopicSeeder extends Seeder
{
    private const TOPICS = [
        'Platform usage & navigation',
        'Account & profile management',
        'Properties & units',
        'Listings',
        'Leads & inquiries',
        'Tenants & rent management',
        'Agents, tasks & commission',
        'Maintenance & repairs',
        'House sales',
        'Reports & exports',
        'Notifications & communication',
        'Security, privacy & verification',
        'Bugs, errors & technical issues',
        'Feedback, suggestions & other',
    ];

    public function run(): void
    {
        foreach (self::TOPICS as $topic) {
            DB::table('supports')->updateOrInsert(['topic' => $topic], ['updated_at' => now(), 'created_at' => now()]);
        }
    }
}
