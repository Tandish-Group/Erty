<?php

namespace App\Services\Tenancy;

use App\Models\TenantManagement;
use App\Models\TenantNotice;
use App\Services\Rent\PaymentImport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Importing notices already given outside Erty (Tenants → Import data → notices), for the record: they are kept
 * on the tenancy as delivered and are not sent again. Each staged row is checked the same way on the review page
 * and when it is imported. The tenancy is found the same way as for payments, within this business and the
 * person's properties.
 */
class NoticeImport
{
    public const TYPES = [
        'lease_termination' => 'Lease termination', 'rent_increase' => 'Rent increase', 'lease_violation' => 'Lease violation',
        'property_inspection' => 'Property inspection', 'maintenance_notice' => 'Maintenance notice', 'eviction_notice' => 'Eviction notice',
        'lease_renewal' => 'Lease renewal', 'other' => 'Other',
    ];

    public function __construct(private PaymentImport $lookup)
    {
    }

    public function staged(int $ownerId)
    {
        return DB::table('temp_tenant_notices')->where('user_id', $ownerId)->orderBy('row_number')->get()
            ->map(fn ($row) => (object) (['row' => $row] + $this->check($row)));
    }

    /** @return array{tenancy: ?TenantManagement, type: ?string, problems: string[]} */
    public function check(object $row): array
    {
        $problems = $row->processing_status === 'failed' && $row->error_message ? [rtrim($row->error_message, '.')] : [];
        $tenancy = $this->lookup->tenancyFor($row, $problems);
        if (!$tenancy && !$problems) {
            $problems[] = 'no tenancy reference, tenant reference or e-mail';
        }
        $type = self::type($row->notice_type);
        if (!$type) {
            $problems[] = $row->notice_type ? '"' . $row->notice_type . '" is not a notice type' : 'no notice type';
        }
        if (!$row->notice_date) {
            $problems[] = 'no notice date';
        } elseif (Carbon::parse($row->notice_date)->isAfter(today())) {
            $problems[] = 'the notice date is in the future (give future notices on the tenancy page)';
        }
        if (!$row->effective_date) {
            $problems[] = 'no effective date';
        } elseif ($row->notice_date && Carbon::parse($row->effective_date)->lt(Carbon::parse($row->notice_date))) {
            $problems[] = 'the effective date is before the notice date';
        }

        return ['tenancy' => $tenancy, 'type' => $type, 'problems' => $problems];
    }

    public function import(object $row): TenantNotice
    {
        $c = $this->check($row);
        if ($c['problems']) {
            throw new \RuntimeException(ucfirst(implode('; ', $c['problems'])) . '.');
        }
        $methods = collect(preg_split('/[\s,;]+/', strtolower((string) $row->delivery_methods)))
            ->map(fn ($m) => ['post' => 'physical', 'hand' => 'physical', 'letter' => 'physical', 'e-mail' => 'email', 'text' => 'sms'][$m] ?? $m)
            ->filter(fn ($m) => in_array($m, ['email', 'sms', 'physical'], true))->unique()->values()->all();

        return TenantNotice::create([
            'tenant_id' => $c['tenancy']->id,
            'issued_by' => auth()->id(),
            'notice_type' => $c['type'],
            'notice_date' => $row->notice_date,
            'effective_date' => $row->effective_date,
            'notice_period' => $row->notice_period ?: Carbon::parse($row->notice_date)->diffInDays(Carbon::parse($row->effective_date)),
            'notice_subject' => $row->notice_subject ?: self::TYPES[$c['type']],
            'notice_details' => $row->notice_details ?: ($row->notice_subject ?: self::TYPES[$c['type']]) . ' (imported).',
            'delivery_methods' => $methods ?: ['physical'],
            'delivery_status' => 'delivered',
            'delivered_at' => Carbon::parse($row->notice_date),
        ]);
    }

    /** A notice type as stored, accepting labels such as "Lease termination". */
    public static function type(?string $value): ?string
    {
        $key = str_replace([' ', '-'], '_', strtolower(trim((string) $value)));
        $aliases = ['termination' => 'lease_termination', 'notice_to_vacate' => 'lease_termination', 'vacate' => 'lease_termination',
            'eviction' => 'eviction_notice', 'inspection' => 'property_inspection', 'maintenance' => 'maintenance_notice',
            'renewal' => 'lease_renewal', 'violation' => 'lease_violation', 'increase' => 'rent_increase'];
        $key = $aliases[$key] ?? $key;

        return isset(self::TYPES[$key]) ? $key : null;
    }
}
