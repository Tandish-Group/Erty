<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * SQL fragments that differ between the databases Erty runs on (PostgreSQL in production, SQLite in tests,
 * MySQL for anyone who prefers it). Column names are passed in by the caller and never come from user input.
 */
final class Sql
{
    /** Seconds from $from to $to, as a number. */
    public static function secondsBetween(string $from, string $to, ?string $connection = null): string
    {
        return match (DB::connection($connection)->getDriverName()) {
            'pgsql' => "EXTRACT(EPOCH FROM ({$to} - {$from}))",
            'sqlite' => "((julianday({$to}) - julianday({$from})) * 86400)",
            default => "TIMESTAMPDIFF(SECOND, {$from}, {$to})",
        };
    }

    public static function hoursBetween(string $from, string $to, ?string $connection = null): string
    {
        return '(' . self::secondsBetween($from, $to, $connection) . ' / 3600.0)';
    }

    public static function daysBetween(string $from, string $to, ?string $connection = null): string
    {
        return '(' . self::secondsBetween($from, $to, $connection) . ' / 86400.0)';
    }
}
