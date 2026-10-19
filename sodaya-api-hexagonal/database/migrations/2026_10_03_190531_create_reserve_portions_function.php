<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Create the function that takes portions from several dishes of a soda at once, or from none:
     * it locks the dishes in the order of their identifiers and answers whether it reserved.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_reserve_portions(p_soda_id uuid, p_dish_ids uuid[], p_quantities integer[])
                RETURNS boolean
                LANGUAGE plpgsql
                SECURITY INVOKER
                SET search_path = public
            AS $$
            BEGIN
                PERFORM 1
                   FROM dishes
                  WHERE soda_id = p_soda_id
                    AND id = ANY (p_dish_ids)
                  ORDER BY id
                    FOR UPDATE;

                IF EXISTS (
                    SELECT 1
                      FROM unnest(p_dish_ids, p_quantities) AS wanted (dish_id, quantity)
                      LEFT JOIN dishes d ON d.id = wanted.dish_id AND d.soda_id = p_soda_id AND d.is_active
                     WHERE d.id IS NULL
                        OR d.available_portions < wanted.quantity
                ) THEN
                    RETURN false;
                END IF;

                UPDATE dishes d
                   SET available_portions = d.available_portions - wanted.quantity,
                       updated_at = now()
                  FROM unnest(p_dish_ids, p_quantities) AS wanted (dish_id, quantity)
                 WHERE d.id = wanted.dish_id
                   AND d.soda_id = p_soda_id;

                RETURN true;
            END;
            $$;
            SQL);
    }

    /** Drop the function. */
    public function down(): void
    {
        DB::statement('DROP FUNCTION fn_reserve_portions(uuid, uuid[], integer[])');
    }
};
