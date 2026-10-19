<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tables a customer reads through their order: each one gets a read-only policy next to its soda isolation.
     *
     * @var list<string>
     */
    private const array THROUGH_ORDER = ['order_lines', 'order_status_history'];

    /**
     * Let a transaction that names a customer, app.customer_id, read the orders of that customer with their lines
     * and their history, whatever the soda. The policies only grant SELECT: a customer never writes without the soda
     * of the order set, so every write keeps going through the isolation policy of the soda.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE POLICY orders_customer_access ON orders FOR SELECT
                USING (customer_id = NULLIF(current_setting('app.customer_id', true), '')::uuid)
            SQL);

        foreach (self::THROUGH_ORDER as $table) {
            DB::statement(<<<SQL
                CREATE POLICY {$table}_customer_access ON {$table} FOR SELECT
                    USING (EXISTS (
                        SELECT 1 FROM orders
                        WHERE orders.id = {$table}.order_id
                          AND orders.customer_id = NULLIF(current_setting('app.customer_id', true), '')::uuid
                    ))
                SQL);
        }
    }

    /** Drop the read policies of the customer; the isolation policies of the soda stay. */
    public function down(): void
    {
        foreach ([...self::THROUGH_ORDER, 'orders'] as $table) {
            DB::statement("DROP POLICY {$table}_customer_access ON {$table}");
        }
    }
};
