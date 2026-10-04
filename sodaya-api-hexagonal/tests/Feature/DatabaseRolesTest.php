<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class DatabaseRolesTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    public function test_application_role_has_no_elevated_attributes(): void
    {
        $role = DB::selectOne('select rolsuper, rolbypassrls,rolcreatedb,rolcreaterole from pg_roles where rolname = current_user');
        $this->assertNotNull($role);
        $this->assertSame([false, false, false, false], array_values((array) $role));
    }

    public function test_application_role_owns_no_table(): void
    {
        $owned = DB::scalar("select count(*) from pg_tables where schemaname = 'public' and tableowner = current_user");
        $this->assertSame(0, $owned);
    }

    public function test_application_role_reads_and_writes_the_tables(): void
    {
        DB::table('cache')->insert(['key' => 'role-check', 'value' => 'ok', 'expiration' => 0]);
        $this->assertSame('ok', DB::table('cache')->where('key', 'role-check')->value('value'));
    }

    public function test_application_role_cannot_change_the_schema(): void
    {
        $this->expectException(QueryException::class);
        DB::statement('create table forbidden (id integer)');
    }
}
