<?php

namespace Tests\Unit;

use App\Support\Sql;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SqlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sql_test', 'database.connections.sql_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    }

    public function test_date_differences_work_on_sqlite(): void
    {
        $row = DB::selectOne('select ' . Sql::daysBetween("'2026-01-01 00:00:00'", "'2026-01-03 12:00:00'") . ' as d, '
            . Sql::hoursBetween("'2026-01-01 00:00:00'", "'2026-01-01 06:30:00'") . ' as h');
        $this->assertEqualsWithDelta(2.5, (float) $row->d, 0.0001);
        $this->assertEqualsWithDelta(6.5, (float) $row->h, 0.0001);
    }

    public function test_postgres_and_mysql_fragments(): void
    {
        $shape = fn (string $driver) => ['driver' => $driver, 'database' => 'unused', 'prefix' => '', 'host' => '127.0.0.1'];
        config(['database.connections.pg_shape' => $shape('pgsql'), 'database.connections.my_shape' => $shape('mysql')]);
        $this->assertSame('(EXTRACT(EPOCH FROM (b - a)) / 3600.0)', Sql::hoursBetween('a', 'b', 'pg_shape'));
        $this->assertSame('(TIMESTAMPDIFF(SECOND, a, b) / 86400.0)', Sql::daysBetween('a', 'b', 'my_shape'));
    }
}
