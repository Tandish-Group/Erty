<?php

namespace App\Support;

/**
 * Lease status for tables: a record with agreement_type, status, lease_start_date and lease_end_date
 * (a tenancy, or its lease). Anything that is not a dated lease keeps its own status.
 */
final class LeaseStatus
{
    public static function of(?object $record): ?string
    {
        if (($record?->agreement_type ?? null) !== 'lease' || !($record->lease_end_date ?? null)) {
            return $record?->status;
        }

        $now = now();
        if ($now->lt($record->lease_start_date)) {
            return 'Upcoming';
        }
        if ($now->gt($record->lease_end_date)) {
            return 'Expired';
        }

        $days = $now->diffInDays($record->lease_end_date, false);
        return ($days <= 30 && $days > 0) ? 'Expiring Soon' : 'Active';
    }

    /** Days until the lease ends (negative once it has ended); null when it is not a dated lease. */
    public static function daysLeft(?object $record): ?float
    {
        if (($record?->agreement_type ?? null) !== 'lease' || !($record->lease_end_date ?? null)) {
            return null;
        }

        return now()->diffInDays($record->lease_end_date, false);
    }
}
