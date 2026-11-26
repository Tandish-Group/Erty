<?php

// One catalogue for the facility forms, summaries and validation.
return [
    'parking' => ['label' => 'Parking', 'group' => 'Spaces & access', 'icon' => 'parking', 'help' => 'Record capacity and label the spaces that can be assigned to tenants.', 'fields' => [
        'parking_slots' => ['label' => 'Total parking spaces', 'type' => 'number', 'min' => 1],
        'parking_ids' => ['label' => 'Space labels', 'type' => 'textarea', 'help' => 'Separate labels with commas, for example RG-P01, RG-P02. Use property-specific labels. Existing occupied spaces must be retained.'],
    ]],
    'security' => ['label' => 'Security', 'group' => 'Safety', 'icon' => 'shield-alt', 'help' => 'Describe the security service or access control provided.', 'fields' => [
        'security_type' => ['label' => 'Security arrangement', 'type' => 'select', 'options' => ['guarded' => 'Security guards', '24_hour' => '24-hour security', 'gate_access' => 'Gate access control', 'cctv' => 'CCTV monitoring']],
    ]],
    'cctv' => ['label' => 'CCTV surveillance', 'group' => 'Safety', 'icon' => 'video', 'help' => 'Record the areas covered by cameras.', 'fields' => [
        'cctv_coverage' => ['label' => 'Coverage areas', 'type' => 'text', 'placeholder' => 'Entrances, corridors and parking'],
    ]],
    'water' => ['label' => 'Water supply', 'group' => 'Utilities', 'icon' => 'tint', 'help' => 'Record the property’s water source.', 'fields' => [
        'water_type' => ['label' => 'Water source', 'type' => 'select', 'options' => ['municipal' => 'Municipal supply', 'borehole' => 'Borehole', 'both' => 'Municipal supply + borehole']],
    ]],
    'backup_power' => ['label' => 'Backup power', 'group' => 'Utilities', 'icon' => 'bolt', 'help' => 'Record the backup power available during an outage.', 'fields' => [
        'backup_type' => ['label' => 'Power source', 'type' => 'select', 'options' => ['generator' => 'Generator', 'solar' => 'Solar', 'ups' => 'UPS']],
    ]],
    'internet' => ['label' => 'Internet', 'group' => 'Utilities', 'icon' => 'wifi', 'help' => 'Record the shared connection, provider and advertised speed.', 'fields' => [
        'internet_provider' => ['label' => 'Provider', 'type' => 'text'],
        'internet_speed' => ['label' => 'Speed (Mbps)', 'type' => 'number', 'min' => 1],
    ]],
    'garbage_collection' => ['label' => 'Waste collection', 'group' => 'Upkeep', 'icon' => 'trash-alt', 'help' => 'Record how often waste is collected.', 'fields' => [
        'garbage_frequency' => ['label' => 'Collection frequency', 'type' => 'select', 'options' => ['daily' => 'Daily', 'weekly' => 'Weekly', 'bi-weekly' => 'Every two weeks']],
    ]],
    'cleaning' => ['label' => 'Cleaning', 'group' => 'Upkeep', 'icon' => 'broom', 'help' => 'Describe the cleaning schedule for shared spaces.', 'fields' => [
        'cleaning_schedule' => ['label' => 'Cleaning schedule', 'type' => 'text', 'placeholder' => 'Common corridors daily, windows monthly'],
    ]],
    'landscaping' => ['label' => 'Landscaping', 'group' => 'Upkeep', 'icon' => 'leaf', 'help' => 'Describe garden and outdoor maintenance.', 'fields' => [
        'landscaping_description' => ['label' => 'Areas and maintenance', 'type' => 'textarea'],
    ]],
    'elevator' => ['label' => 'Lifts / elevators', 'group' => 'Spaces & access', 'icon' => 'arrows-alt-v', 'help' => 'Record the lifts serving the property.', 'fields' => [
        'elevator_count' => ['label' => 'Number of lifts', 'type' => 'number', 'min' => 1],
    ]],
    'gym' => ['label' => 'Gym', 'group' => 'Shared amenities', 'icon' => 'dumbbell', 'help' => 'Describe the equipment, access and opening hours.', 'fields' => [
        'gym_description' => ['label' => 'Gym details', 'type' => 'textarea'],
    ]],
    'conference_room' => ['label' => 'Conference room', 'group' => 'Shared amenities', 'icon' => 'users', 'help' => 'Record the room’s seating capacity.', 'fields' => [
        'conference_capacity' => ['label' => 'Capacity (people)', 'type' => 'number', 'min' => 1],
    ]],
    'loading_bay' => ['label' => 'Loading bay', 'group' => 'Spaces & access', 'icon' => 'truck', 'help' => 'Describe the bay size and vehicle access.', 'fields' => [
        'loading_bay_size' => ['label' => 'Size / vehicle access', 'type' => 'text', 'placeholder' => 'Two delivery vans or one medium truck'],
    ]],
    'common_area_water' => ['label' => 'Shared-area water', 'group' => 'Utilities', 'icon' => 'tint', 'help' => 'Record water used for shared areas such as gardens and cleaning.', 'fields' => [
        'common_area_water_description' => ['label' => 'Areas served', 'type' => 'textarea'],
    ]],
    'common_area_electricity' => ['label' => 'Shared-area electricity', 'group' => 'Utilities', 'icon' => 'lightbulb', 'help' => 'Record electricity for shared lighting and equipment.', 'fields' => [
        'common_area_electricity_description' => ['label' => 'Areas / equipment served', 'type' => 'textarea'],
    ]],
];
