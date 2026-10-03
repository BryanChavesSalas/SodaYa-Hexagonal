<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait RefreshDatabaseAsOwner
{
    use RefreshDatabase {
        migrateFreshUsing as private frameworkMigrateFreshUsing;
    }
    protected function migrateFreshUsing(): array
    {
        return [...$this->frameworkMigrateFreshUsing(), '--database' => 'pgsql_admin'];
    }
}
