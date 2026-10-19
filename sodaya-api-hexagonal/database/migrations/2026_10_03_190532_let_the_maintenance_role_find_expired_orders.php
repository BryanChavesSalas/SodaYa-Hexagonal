<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Let the maintenance role read the orders of every soda, and index the pending ones by creation time. */
    public function up(): void
    {
        DB::statement("GRANT SELECT ON orders TO {$this->maintenanceRole()}");
        DB::statement("CREATE INDEX orders_pending_created_at_index ON orders (created_at) WHERE status = 'pending'");
    }

    /** Remove the index and the privilege. */
    public function down(): void
    {
        DB::statement('DROP INDEX orders_pending_created_at_index');
        DB::statement("REVOKE SELECT ON orders FROM {$this->maintenanceRole()}");
    }

    /** Role the maintenance connection logs in with, quoted as an identifier. */
    private function maintenanceRole(): string
    {
        $name = config('database.connections.pgsql_maintenance.username');

        return '"'.str_replace('"', '""', is_string($name) ? $name : '').'"';
    }
};
