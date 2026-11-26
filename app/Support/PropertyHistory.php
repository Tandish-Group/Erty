<?php

namespace App\Support;

use App\Models\ComplexHistory;
use Carbon\Carbon;

/**
 * What can be recorded in a property's history, in one place: each category's fields (the form input name and
 * the key it is stored under in complex_histories.data), which field is the event date, the file field, and how a
 * record reads back (title, facts, files). The add-record form, validation and every display use this.
 */
class PropertyHistory
{
    /**
     * field: [input name, data key, label, type (text|textarea|date|number|money|select), options (value => label), required]
     */
    public static function categories(): array
    {
        $opts = fn (array $values, array $labels = []) => collect($values)->mapWithKeys(fn ($v) => [$v => $labels[$v] ?? ucfirst(str_replace('_', ' ', $v))])->all();

        return [
            'ownership' => [
                'icon' => 'bi-key', 'tone' => 'teal', 'short' => 'Who owned it, and when ownership changed',
                'date' => 'transfer_date', 'files' => 'supporting_docs', 'files_label' => 'Title deed, transfer or sale agreement',
                'fields' => [
                    ['previous_owner', 'previous_owner', 'Previous owner or landlord', 'text', null, true],
                    ['ownership_type', 'ownership_type', 'Ownership type', 'select', $opts(['freehold', 'leasehold', 'cooperative', 'condominium']), true],
                    ['ownership_method', 'ownership_method', 'How ownership passed', 'select', $opts(['purchase', 'inheritance', 'public_auction', 'gift', 'court_order', 'other']), true],
                    ['transfer_date', 'transfer_date', 'Transfer date', 'date', null, true],
                    ['ownership_end_date', 'ownership_end_date', 'Ownership ended', 'date', null, false],
                ],
            ],
            'renovation' => [
                'icon' => 'bi-hammer', 'tone' => 'amber', 'short' => 'Upgrades and remodelling: kitchens, roofs, floors',
                'date' => 'completion_date', 'files' => 'renovation_images', 'files_label' => 'Before and after photos, permits, invoices',
                'fields' => [
                    ['renovation_type', 'renovation_type', 'What was renovated', 'select', $opts(['kitchen', 'bathroom', 'roof', 'flooring', 'electrical', 'plumbing', 'full_renovation', 'other']), true],
                    ['completion_date', 'completion_date', 'Completed on', 'date', null, true],
                    ['cost_estimate', 'cost_estimate', 'Cost', 'money', null, false],
                    ['contractor_info', 'contractor_info', 'Contractor', 'textarea', null, false],
                    ['permit_reference', 'permit_reference', 'Permit reference', 'text', null, false],
                ],
            ],
            'structural' => [
                'icon' => 'bi-bricks', 'tone' => 'rose', 'short' => 'Extensions, demolitions and layout changes',
                'date' => 'completion_date', 'files' => 'structural_docs', 'files_label' => 'Approvals and architectural drawings',
                'fields' => [
                    ['structural_type', 'structural_type', 'Type of change', 'select', $opts(['extension', 'addition', 'reconstruction', 'layout_change', 'structural_repair']), true],
                    ['structural_date', 'completion_date', 'Completed on', 'date', null, true],
                    ['property_value_impact', 'property_value_impact', 'Effect on value', 'select', $opts(['increased', 'decreased', 'no_change', 'unknown']), false],
                    ['structural_description', 'description', 'What changed', 'textarea', null, true],
                ],
            ],
            'legal' => [
                'icon' => 'bi-bank', 'tone' => 'slate', 'short' => 'Disputes, certificates, mortgages and liens',
                'date' => 'date', 'files' => 'legal_docs', 'files_label' => 'Court papers, certificates, charge documents',
                'fields' => [
                    ['legal_type', 'event_type', 'Legal event', 'select', $opts(['land_dispute', 'litigation', 'compliance_certificate', 'nema_certificate', 'land_use_certificate', 'mortgage', 'lien'], ['nema_certificate' => 'NEMA certificate']), true],
                    ['legal_status', 'status', 'Status', 'select', $opts(['active', 'pending', 'resolved', 'expired']), true],
                    ['legal_date', 'date', 'Date', 'date', null, true],
                    ['case_reference', 'case_reference', 'Case or reference number', 'text', null, false],
                    ['jurisdiction_authority', 'jurisdiction_authority', 'Court or authority', 'text', null, false],
                    ['legal_details', 'details', 'Details', 'textarea', null, true],
                ],
            ],
            'utility' => [
                'icon' => 'bi-lightning-charge', 'tone' => 'sky', 'short' => 'Water, power, solar, sewer and internet connections',
                'date' => 'installation_date', 'files' => 'utility_docs', 'files_label' => 'Connection letters, meter photos, bills',
                'fields' => [
                    ['utility_type', 'utility_type', 'Utility', 'select', $opts(['water', 'electricity', 'solar_installation', 'sewer_system', 'septic_system', 'internet_fiber', 'gas'], ['internet_fiber' => 'Internet (fibre)', 'solar_installation' => 'Solar']), true],
                    ['utility_date', 'installation_date', 'Connected or installed on', 'date', null, true],
                    ['utility_provider', 'provider', 'Provider', 'text', null, false],
                    ['meter_number', 'meter_number', 'Meter number', 'text', null, false],
                    ['connection_reference', 'connection_reference', 'Account or connection reference', 'text', null, false],
                    ['utility_details', 'details', 'Details', 'textarea', null, true],
                ],
            ],
            'sales_rental' => [
                'icon' => 'bi-tags', 'tone' => 'green', 'short' => 'Past sales, lettings and vacant periods',
                'date' => 'date', 'files' => 'sales_rental_docs', 'files_label' => 'Sale or lease agreements',
                'fields' => [
                    ['sales_rental_type', 'record_type', 'Record', 'select', $opts(['sale_record', 'rental_record', 'vacancy_period'], ['sale_record' => 'Sale', 'rental_record' => 'Letting', 'vacancy_period' => 'Vacant period']), true],
                    ['sales_date', 'date', 'Date', 'date', null, true],
                    ['price_rate', 'price_rate', 'Price or rent', 'money', null, true],
                    ['payment_method', 'payment_method', 'Paid by', 'select', $opts(['cash', 'mortgage', 'bank_transfer', 'rent_deposit', 'other']), false],
                    ['rental_duration', 'rental_duration', 'Lease length', 'text', null, false],
                    ['tenant_buyer_info', 'tenant_buyer_info', 'Buyer or tenant', 'textarea', null, false],
                ],
            ],
            'documentation' => [
                'icon' => 'bi-file-earmark-text', 'tone' => 'indigo', 'short' => 'Title deeds, survey plans, certificates',
                'date' => 'issue_date', 'files' => 'document_files', 'files_label' => 'The document itself',
                'fields' => [
                    ['document_type', 'document_type', 'Document', 'select', $opts(['title_deed', 'tax_clearance_certificate', 'survey_plan', 'mutation_form', 'occupancy_certificate', 'other']), true],
                    ['document_title', 'title', 'Title', 'text', null, true],
                    ['document_date', 'issue_date', 'Issued on', 'date', null, true],
                    ['document_expiry', 'expiry_date', 'Expires on', 'date', null, false],
                    ['issuing_authority', 'issuing_authority', 'Issued by', 'text', null, false],
                ],
            ],
            'incident' => [
                'icon' => 'bi-exclamation-triangle', 'tone' => 'red', 'short' => 'Fire, flooding, theft and storm damage',
                'date' => 'date', 'files' => 'incident_docs', 'files_label' => 'Photos, police abstract, insurance papers',
                'fields' => [
                    ['incident_type', 'incident_type', 'What happened', 'select', $opts(['flooding', 'fire', 'theft', 'earthquake', 'storm', 'other_disaster'], ['other_disaster' => 'Other']), true],
                    ['incident_date', 'date', 'Date', 'date', null, true],
                    ['recovery_status', 'recovery_status', 'Repairs', 'select', $opts(['repaired', 'partial_repair', 'ongoing_repairs', 'not_repaired']), false],
                    ['insurance_claim', 'insurance_claim', 'Insurance claim', 'select', $opts(['yes', 'no', 'pending'], ['yes' => 'Claimed', 'no' => 'No claim']), false],
                    ['police_report_number', 'police_report_number', 'Police report (OB) number', 'text', null, false],
                    ['incident_description', 'description', 'Description', 'textarea', null, true],
                ],
            ],
            'zoning' => [
                'icon' => 'bi-map', 'tone' => 'violet', 'short' => 'Changes to permitted land use and planning',
                'date' => 'change_date', 'files' => 'zoning_docs', 'files_label' => 'Gazette notice, approvals',
                'fields' => [
                    ['original_zoning', 'original_zoning', 'Zoned before', 'text', null, true],
                    ['updated_zoning', 'updated_zoning', 'Zoned after', 'text', null, true],
                    ['zoning_change_date', 'change_date', 'Changed on', 'date', null, true],
                    ['gazette_reference', 'gazette_reference', 'Gazette reference', 'text', null, false],
                    ['consultation_held', 'consultation_held', 'Public consultation', 'select', $opts(['yes', 'no', 'unknown'], ['yes' => 'Held', 'no' => 'Not held']), false],
                    ['future_planning', 'future_planning', 'Planned developments nearby', 'textarea', null, false],
                ],
            ],
            'media' => [
                'icon' => 'bi-images', 'tone' => 'pink', 'short' => 'Old photos, floor plans, drawings and renders',
                'date' => 'date', 'files' => 'media_files', 'files_label' => 'Photos, drawings or video',
                'fields' => [
                    ['media_type', 'media_type', 'Media', 'select', $opts(['old_listing_photos', 'floor_plans', 'architectural_drawings', 'renders', 'progress_photos', 'other']), true],
                    ['media_date', 'date', 'Date taken or made', 'date', null, true],
                    ['media_format', 'format', 'Format', 'select', $opts(['image', 'pdf', 'dwg', 'mp4', 'other'], ['pdf' => 'PDF', 'dwg' => 'DWG (AutoCAD)', 'mp4' => 'MP4 video']), false],
                    ['media_source', 'source', 'Source', 'text', null, false],
                    ['media_description', 'description', 'Description', 'textarea', null, true],
                ],
            ],
        ];
    }

    /** Short names for each category (the stored category names are long). */
    public const LABELS = [
        'ownership' => 'Ownership', 'renovation' => 'Renovation', 'structural' => 'Structural change', 'legal' => 'Legal',
        'utility' => 'Utilities', 'sales_rental' => 'Sale or letting', 'documentation' => 'Documents', 'incident' => 'Incident',
        'zoning' => 'Zoning & planning', 'media' => 'Photos & plans',
    ];

    public static function category(?string $tag): ?array
    {
        $c = self::categories()[$tag] ?? null;
        return $c ? $c + ['label' => self::LABELS[$tag]] : null;
    }

    public static function label(?string $tag): string
    {
        return self::LABELS[$tag] ?? ucfirst(str_replace('_', ' ', (string) $tag));
    }

    /** Validation rules for one category's fields, keyed by input name. */
    public static function rules(string $tag): array
    {
        $rules = [];
        foreach (self::category($tag)['fields'] ?? [] as [$input, , , $type, $options, $required]) {
            $rule = [$required ? 'required' : 'nullable'];
            $rule[] = match ($type) {
                'date' => 'date',
                'money', 'number' => 'numeric',
                'textarea' => 'string|max:2000',
                default => 'string|max:255',
            };
            if (in_array($type, ['money', 'number'], true)) $rule[] = 'min:0';
            if ($options) $rule[] = 'in:' . implode(',', array_keys($options));
            $rules[$input] = implode('|', $rule);
        }
        // An end can't come before its start.
        if ($tag === 'ownership') $rules['ownership_end_date'] .= '|after:transfer_date';
        if ($tag === 'documentation') $rules['document_expiry'] .= '|after:document_date';
        return $rules;
    }

    /** The stored data for a category from the request's input. */
    public static function structure(string $tag, array $input): array
    {
        $data = [];
        foreach (self::category($tag)['fields'] as [$name, $key]) {
            $data[$key] = $input[$name] ?? null;
        }
        return $data;
    }

    /** The event's own date (not when it was recorded). */
    public static function eventDate(ComplexHistory $record): ?Carbon
    {
        $key = self::category($record->category_tag)['date'] ?? null;
        $value = $key ? ($record->data[$key] ?? null) : null;
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function option(string $tag, string $key, $value): ?string
    {
        foreach (self::category($tag)['fields'] ?? [] as [, $k, , , $options]) {
            if ($k === $key) return $options[$value] ?? ($value === null ? null : ucfirst(str_replace('_', ' ', (string) $value)));
        }
        return $value;
    }

    /** One line that says what happened: "Freehold, from J. Mwangi", "Kitchen renovation", "Fire". */
    public static function title(ComplexHistory $record): string
    {
        $d = is_array($record->data) ? $record->data : [];
        $o = fn ($key) => self::option($record->category_tag, $key, $d[$key] ?? null);
        $money = fn ($v) => is_numeric($v) ? money($v, null, 0) : null;
        return match ($record->category_tag) {
            'ownership' => trim(($o('ownership_type') ?: 'Ownership') . ($d['previous_owner'] ?? null ? ', from ' . $d['previous_owner'] : '')),
            'renovation' => ($o('renovation_type') ?: 'Renovation') . (($d['renovation_type'] ?? '') === 'full_renovation' ? '' : ' renovation'),
            'structural' => $o('structural_type') ?: 'Structural change',
            'legal' => trim(($o('event_type') ?: 'Legal event') . ($d['status'] ?? null ? ' · ' . $o('status') : '')),
            'utility' => ($o('utility_type') ?: 'Utility') . ' connection',
            'sales_rental' => trim(($o('record_type') ?: 'Sale or letting') . ($money($d['price_rate'] ?? null) ? ' · ' . $money($d['price_rate']) : '')),
            'documentation' => ($d['title'] ?? null) ?: ($o('document_type') ?: 'Document'),
            'incident' => $o('incident_type') ?: 'Incident',
            'zoning' => trim(($d['original_zoning'] ?? '?') . ' → ' . ($d['updated_zoning'] ?? '?')),
            'media' => $o('media_type') ?: 'Media',
            default => $record->category?->name ?? 'Property event',
        };
    }

    /** The recorded facts as [label, value] pairs (empty ones left out); text fields come back separately. */
    public static function facts(ComplexHistory $record): array
    {
        $d = is_array($record->data) ? $record->data : [];
        $facts = [];
        $texts = [];
        foreach (self::category($record->category_tag)['fields'] ?? [] as [, $key, $label, $type, $options]) {
            $value = $d[$key] ?? null;
            if ($value === null || $value === '') continue;
            $shown = match ($type) {
                'date' => self::formatDate($value),
                'money' => is_numeric($value) ? default_currency() . ' ' . number_format((float) $value) : $value,
                'select' => $options[$value] ?? ucfirst(str_replace('_', ' ', (string) $value)),
                default => $value,
            };
            if ($type === 'textarea') $texts[] = [$label, $shown];
            else $facts[] = [$label, $shown];
        }
        if (!empty($d['sale_reference'])) $facts[] = ['Sale', $d['sale_reference']];
        return ['facts' => $facts, 'texts' => $texts];
    }

    /** A short preview for the timeline: the first text, or the first facts. */
    public static function summary(ComplexHistory $record): ?string
    {
        $parts = self::facts($record);
        if ($parts['texts']) return (string) $parts['texts'][0][1];
        $title = mb_strtolower(self::title($record));
        return collect($parts['facts'])
            ->reject(fn ($f) => str_contains(strtolower($f[0]), 'date') || str_ends_with(strtolower($f[0]), ' on') || str_contains($title, mb_strtolower((string) $f[1])))
            ->take(2)->map(fn ($f) => $f[0] . ': ' . $f[1])->implode(' · ') ?: null;
    }

    /** Attachments as [name, url, size, type]. */
    public static function files(ComplexHistory $record): array
    {
        $out = [];
        foreach ((array) ($record->files ?? []) as $group) {
            foreach ((array) $group as $file) {
                if (isset($file['path'], $file['original_name'])) {
                    $out[] = ['name' => $file['original_name'], 'url' => asset('storage/' . $file['path']), 'size' => (int) ($file['size'] ?? 0), 'type' => $file['mime_type'] ?? ''];
                }
            }
        }
        return $out;
    }

    private static function formatDate($value): string
    {
        try {
            return Carbon::parse($value)->format('j M Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
