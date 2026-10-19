<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Create the procedure that puts the portions of every dish back to its base, one soda per transaction,
     * and let only the maintenance role run it.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE PROCEDURE sp_reset_daily_portions()
                LANGUAGE plpgsql
                SECURITY INVOKER
            AS $$
            DECLARE
                soda record;
            BEGIN
                FOR soda IN SELECT id FROM public.sodas ORDER BY id LOOP
                    UPDATE public.dishes
                       SET available_portions = base_portions, updated_at = now()
                     WHERE soda_id = soda.id
                       AND available_portions <> base_portions;

                    COMMIT;
                END LOOP;
            END;
            $$;
            SQL);

        $role = $this->maintenanceRole();
        DB::statement('REVOKE ALL ON PROCEDURE sp_reset_daily_portions() FROM PUBLIC');
        DB::statement("GRANT EXECUTE ON PROCEDURE sp_reset_daily_portions() TO {$role}");
        DB::statement("GRANT SELECT ON sodas, dishes TO {$role}");
        DB::statement("GRANT UPDATE (available_portions, updated_at) ON dishes TO {$role}");
    }

    /** Drop the procedure and the privileges of the maintenance role. */
    public function down(): void
    {
        $role = $this->maintenanceRole();
        DB::statement("REVOKE UPDATE (available_portions, updated_at) ON dishes FROM {$role}");
        DB::statement("REVOKE SELECT ON sodas, dishes FROM {$role}");
        DB::statement('DROP PROCEDURE sp_reset_daily_portions()');
    }

    /** Role the maintenance connection logs in with, quoted as an identifier. */
    private function maintenanceRole(): string
    {
        $name = config('database.connections.pgsql_maintenance.username');

        return '"'.str_replace('"', '""', is_string($name) ? $name : '').'"';
    }
};
