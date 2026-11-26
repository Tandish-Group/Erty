<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LeadTaskTemplate;

/** The tasks each lead phase starts with. Safe to run again: rows are matched on phase and title. */
class LeadTaskTemplateSeeder extends Seeder
{
    public function run()
    {
        $templates = [
            // QUALIFICATION PHASE - System Tasks
            [
                'task_type' => 'system_assigned',
                'phase' => 'qualification',
                'title' => 'Acknowledge Lead Assignment',
                'description' => 'Review lead details and acknowledge assignment',
                'instructions' => 'Open the lead profile, review all provided information, and confirm you have received the assignment.',
                'sequence_order' => 1,
                'sla_hours' => 2, // 2 hours
                'is_required' => true,
                'blocks_next_phase' => false,
                'is_active' => true
            ],
            [
                'task_type' => 'system_assigned',
                'phase' => 'qualification',
                'title' => 'Review Lead Details',
                'description' => 'Verify lead information completeness and accuracy',
                'instructions' => 'Check contact details, budget, property preferences, timeline, and special requirements. Flag any missing critical information.',
                'sequence_order' => 2,
                'sla_hours' => 4,
                'is_required' => true,
                'blocks_next_phase' => false,
                'is_active' => true
            ],
            [
                'task_type' => 'system_assigned',
                'phase' => 'qualification',
                'title' => 'Verify Availability',
                'description' => 'Check matching property availability in system',
                'instructions' => 'Search for properties matching lead criteria. Document available options that fit budget and requirements.',
                'sequence_order' => 3,
                'sla_hours' => 8,
                'is_required' => true,
                'blocks_next_phase' => true, // Must verify properties exist
                'is_active' => true
            ],
            [
                'task_type' => 'system_assigned',
                'phase' => 'qualification',
                'title' => 'Initial Contact Attempt',
                'description' => 'Make first contact with lead via preferred method',
                'instructions' => 'Use the lead\'s preferred contact method. Introduce yourself, confirm interest, and schedule detailed discussion.',
                'sequence_order' => 4,
                'sla_hours' => 24,
                'is_required' => true,
                'blocks_next_phase' => true, // Must establish contact
                'is_active' => true
            ],
            [
                'task_type' => 'system_assigned',
                'phase' => 'qualification',
                'title' => 'Update Lead Status',
                'description' => 'Update lead status based on initial qualification',
                'instructions' => 'After initial contact, update lead status to "qualified" if genuine interest confirmed, or update priority/status accordingly.',
                'sequence_order' => 5,
                'sla_hours' => 2,
                'is_required' => true,
                'blocks_next_phase' => true, // Must qualify before engagement
                'is_active' => true
            ],

            // ENGAGEMENT PHASE - System Tasks
            [
                'task_type' => 'system_assigned',
                'phase' => 'engagement',
                'title' => 'Prepare Property Shortlist',
                'description' => 'Create curated list of properties matching lead requirements',
                'instructions' => 'Based on lead\'s budget, preferences, and requirements, prepare a shortlist of 3-5 suitable properties with details and photos.',
                'sequence_order' => 1,
                'sla_hours' => 12,
                'is_required' => true,
                'blocks_next_phase' => false,
                'is_active' => true
            ],
            [
                'task_type' => 'system_assigned',
                'phase' => 'engagement',
                'title' => 'Schedule Property Viewing',
                'description' => 'Arrange property viewing appointment with lead',
                'instructions' => 'Contact lead to schedule viewing of shortlisted properties. Confirm date, time, and meeting location.',
                'sequence_order' => 2,
                'sla_hours' => 24,
                'is_required' => true,
                'blocks_next_phase' => true, // Must schedule viewing
                'is_active' => true
            ],
            [
                'task_type' => 'system_assigned',
                'phase' => 'engagement',
                'title' => 'Conduct Property Viewing',
                'description' => 'Show properties to lead and gather feedback',
                'instructions' => 'Conduct property viewing session. Highlight key features, answer questions, and document lead\'s feedback and preferences.',
                'sequence_order' => 3,
                'sla_hours' => 72,
                'is_required' => true,
                'blocks_next_phase' => true, // Must complete viewing
                'is_active' => true
            ],
            [
                'task_type' => 'system_assigned',
                'phase' => 'engagement',
                'title' => 'Follow Up on Viewing',
                'description' => 'Contact lead after viewing to assess interest',
                'instructions' => 'Follow up within 24 hours of viewing. Address any questions, gauge interest level, and discuss next steps.',
                'sequence_order' => 4,
                'sla_hours' => 24,
                'is_required' => true,
                'blocks_next_phase' => false,
                'is_active' => true
            ],
            [
                'task_type' => 'system_assigned',
                'phase' => 'engagement',
                'title' => 'Negotiate Terms',
                'description' => 'Discuss and negotiate lease/purchase terms',
                'instructions' => 'If lead shows interest, discuss pricing, lease terms, move-in dates, and any special conditions. Document agreed terms.',
                'sequence_order' => 5,
                'sla_hours' => 48,
                'is_required' => true,
                'blocks_next_phase' => true, // Must negotiate before conversion
                'is_active' => true
            ],
            [
                'task_type' => 'system_assigned',
                'phase' => 'engagement',
                'title' => 'Confirm Lead Intent to Proceed',
                'description' => 'Get formal confirmation that lead wants to move forward',
                'instructions' => 'Obtain verbal or written confirmation that lead is ready to proceed with the application process. Update lead status accordingly.',
                'sequence_order' => 6,
                'sla_hours' => 12,
                'is_required' => true,
                'blocks_next_phase' => true, // Must confirm before conversion
                'is_active' => true
            ],

            // CONVERSION PHASE - System Tasks
            [
                'task_type' => 'system_assigned',
                'phase' => 'conversion',
                'title' => 'Initiate Tenant Onboarding',
                'description' => 'Begin formal tenant onboarding process',
                'instructions' => 'Access tenant onboarding form, populate with lead data, and begin collecting required documentation.',
                'sequence_order' => 1,
                'sla_hours' => 4,
                'is_required' => true,
                'blocks_next_phase' => true,
                'is_active' => true
            ],
            [
                'task_type' => 'system_assigned',
                'phase' => 'conversion',
                'title' => 'Verify Tenant Documentation',
                'description' => 'Collect and verify all required tenant documents',
                'instructions' => 'Ensure ID, proof of income, references, and any business documents are collected and verified.',
                'sequence_order' => 2,
                'sla_hours' => 48,
                'is_required' => true,
                'blocks_next_phase' => true,
                'is_active' => true
            ],

            // FINALIZATION PHASE - System Tasks
            [
                'task_type' => 'system_assigned',
                'phase' => 'finalization',
                'title' => 'Activate Tenant Record',
                'description' => 'Finalize tenant activation in system',
                'instructions' => 'Verify tenant record is complete, billing is set up, and tenant status is active.',
                'sequence_order' => 1,
                'sla_hours' => 2,
                'is_required' => true,
                'blocks_next_phase' => false,
                'is_active' => true
            ],
            [
                'task_type' => 'system_assigned',
                'phase' => 'finalization',
                'title' => 'Close Lead Record',
                'description' => 'Mark lead as successfully converted and close workflow',
                'instructions' => 'Update lead status to "closed_won", verify tenant linkage, and complete any final administrative tasks.',
                'sequence_order' => 2,
                'sla_hours' => 1,
                'is_required' => true,
                'blocks_next_phase' => false,
                'is_active' => true
            ]
        ];

        foreach ($templates as $template) {
            LeadTaskTemplate::updateOrCreate(['phase' => $template['phase'], 'title' => $template['title']], $template);
        }
    }
}