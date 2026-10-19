<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class MaintenanceRoleTest extends TestCase
{
    /** The maintenance connection logs in with its own role, the only one that bypasses row-level security. */
    public function test_only_the_maintenance_role_bypasses_row_level_security(): void
    {
        $maintenance = config('database.connections.pgsql_maintenance.username');
        $application = config('database.connections.pgsql.username');

        $this->assertSame($maintenance, DB::connection('pgsql_maintenance')->scalar('select current_user'));
        $this->assertTrue(DB::scalar('select rolbypassrls from pg_roles where rolname = ?', [$maintenance]));
        $this->assertFalse(DB::scalar('select rolbypassrls from pg_roles where rolname = ?', [$application]));
        $this->assertFalse(DB::scalar('select rolsuper from pg_roles where rolname = ?', [$maintenance]));
    }

    /** The maintenance role cannot change the schema: it is not the owner of the database. */
    public function test_maintenance_role_does_not_own_the_schema(): void
    {
        $owner = DB::scalar('select pg_get_userbyid(datdba) from pg_database where datname = current_database()');

        $this->assertNotSame(config('database.connections.pgsql_maintenance.username'), $owner);
    }
}
