<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Create the view of the delivered orders and the function that adds them up hour by hour.
     * The function has no SET search_path, so PostgreSQL can inline it into the query that calls it; it names its schema instead.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE VIEW v_hourly_sales WITH (security_invoker = true) AS
                SELECT o.soda_id,
                       o.id AS order_id,
                       o.created_at,
                       extract(hour FROM o.created_at AT TIME ZONE 'America/Costa_Rica')::integer AS sale_hour,
                       sum(l.unit_price * l.quantity)::bigint AS total
                FROM orders o
                JOIN order_lines l ON l.order_id = o.id AND l.soda_id = o.soda_id
                WHERE o.status = 'delivered'
                GROUP BY o.id, o.soda_id, o.created_at;

            CREATE OR REPLACE FUNCTION fn_hourly_sales(p_soda_id uuid, p_date date, p_min_orders integer DEFAULT 1)
                RETURNS TABLE (sale_hour integer, orders bigint, total bigint)
                LANGUAGE sql
                STABLE
                SECURITY INVOKER
            AS $$
                SELECT v.sale_hour, count(*), sum(v.total)::bigint
                FROM public.v_hourly_sales v
                WHERE v.soda_id = p_soda_id
                  AND v.created_at >= p_date::timestamp AT TIME ZONE 'America/Costa_Rica'
                  AND v.created_at < (p_date + 1)::timestamp AT TIME ZONE 'America/Costa_Rica'
                GROUP BY v.sale_hour
                HAVING count(*) >= p_min_orders
                ORDER BY v.sale_hour;
            $$;
            SQL);
    }

    /** Drop the function and the view. */
    public function down(): void
    {
        DB::statement('DROP FUNCTION fn_hourly_sales(uuid, date, integer)');
        DB::statement('DROP VIEW v_hourly_sales');
    }
};
