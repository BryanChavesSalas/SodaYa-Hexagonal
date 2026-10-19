<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Two roles take part: what the owner grants must be committed before the maintenance role can use it. */
    public $withinTransaction = false;

    /**
     * Precompute the delivered orders and the sales of every soda and day in a materialized view.
     * A refresh runs as the owner of the view, who is subject to the forced policies and would see no row, so the
     * rows come from a SECURITY DEFINER function owned by the maintenance role, the only one that bypasses
     * row-level security. A materialized view has no policies: the application role cannot read it and queries
     * a security barrier view that filters by the soda of the transaction.
     */
    public function up(): void
    {
        $maintenance = $this->role('pgsql_maintenance');
        $application = $this->role('pgsql');

        DB::statement("GRANT SELECT ON order_lines TO {$maintenance}");
        DB::statement("GRANT CREATE ON SCHEMA public TO {$maintenance}");

        DB::connection('pgsql_maintenance')->unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION fn_daily_sales_of_every_soda()
                RETURNS TABLE (soda_id uuid, day date, orders bigint, total bigint)
                LANGUAGE sql
                STABLE
                SECURITY DEFINER
                SET search_path = pg_catalog, public
            BEGIN ATOMIC
                SELECT o.soda_id,
                       (o.created_at AT TIME ZONE 'America/Costa_Rica')::date AS day,
                       count(DISTINCT o.id) AS orders,
                       sum(l.unit_price * l.quantity)::bigint AS total
                FROM orders o
                JOIN order_lines l ON l.order_id = o.id AND l.soda_id = o.soda_id
                WHERE o.status = 'delivered'
                GROUP BY o.soda_id, (o.created_at AT TIME ZONE 'America/Costa_Rica')::date;
            END;

            REVOKE ALL ON FUNCTION fn_daily_sales_of_every_soda() FROM PUBLIC;
            GRANT EXECUTE ON FUNCTION fn_daily_sales_of_every_soda() TO {$this->role('pgsql_admin')};
            SQL);

        DB::statement("REVOKE CREATE ON SCHEMA public FROM {$maintenance}");

        DB::unprepared(<<<SQL
            CREATE MATERIALIZED VIEW mv_daily_sales AS
                SELECT soda_id, day, orders, total FROM fn_daily_sales_of_every_soda();

            CREATE UNIQUE INDEX mv_daily_sales_soda_id_day_unique ON mv_daily_sales (soda_id, day);

            REVOKE ALL ON mv_daily_sales FROM {$application};
            GRANT MAINTAIN ON mv_daily_sales TO {$maintenance};

            CREATE OR REPLACE VIEW v_daily_sales WITH (security_barrier = true) AS
                SELECT soda_id, day, orders, total
                FROM mv_daily_sales
                WHERE soda_id = NULLIF(current_setting('app.soda_id', true), '')::uuid;

            GRANT SELECT ON v_daily_sales TO {$application};
            SQL);
    }

    /** Drop the views and the function, and take the privileges back from the maintenance role. */
    public function down(): void
    {
        DB::statement('DROP VIEW v_daily_sales');
        DB::statement('DROP MATERIALIZED VIEW mv_daily_sales');
        DB::connection('pgsql_maintenance')->statement('DROP FUNCTION fn_daily_sales_of_every_soda()');
        DB::statement("REVOKE SELECT ON order_lines FROM {$this->role('pgsql_maintenance')}");
    }

    /** Role a connection logs in with, quoted as an identifier. */
    private function role(string $connection): string
    {
        $name = config("database.connections.{$connection}.username");

        return '"'.str_replace('"', '""', is_string($name) ? $name : '').'"';
    }
};
