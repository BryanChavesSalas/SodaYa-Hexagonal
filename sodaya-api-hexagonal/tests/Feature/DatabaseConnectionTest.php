<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    public function test_default_connection_uses_postgresql(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }
    public function test_database_accepts_queries(): void
    {
        $this->assertSame(1, DB::scalar('select 1'));
    }
}
