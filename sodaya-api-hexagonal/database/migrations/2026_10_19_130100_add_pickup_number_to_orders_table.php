<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give every order a short pickup number, consecutive per soda and per day of Costa Rica, taken from a counter
     * row per soda and day; and freeze in each line the preparation minutes of its dish, for the estimated pickup time.
     * The orders that already exist are numbered by their creation time.
     */
    public function up(): void
    {
        Schema::create('pickup_counters', function (Blueprint $table): void {
            $table->foreignUuid('soda_id')->constrained()->cascadeOnDelete();
            $table->date('pickup_date');
            $table->smallInteger('last_number')->default(0);

            $table->primary(['soda_id', 'pickup_date']);
        });

        DB::statement('ALTER TABLE pickup_counters ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE pickup_counters FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY pickup_counters_soda_isolation ON pickup_counters
                USING (soda_id = NULLIF(current_setting('app.soda_id', true), '')::uuid)
                WITH CHECK (soda_id = NULLIF(current_setting('app.soda_id', true), '')::uuid)
            SQL);

        Schema::table('orders', function (Blueprint $table): void {
            $table->date('pickup_date')->nullable();
            $table->smallInteger('pickup_number')->nullable();
        });

        Schema::table('order_lines', function (Blueprint $table): void {
            $table->smallInteger('preparation_minutes')->default(15);
        });

        // The owner is subject to the policies too; lifting FORCE inside this transaction lets the backfill reach every soda.
        DB::statement('ALTER TABLE orders NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE pickup_counters NO FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            UPDATE orders o
               SET pickup_date = n.pickup_date,
                   pickup_number = n.pickup_number
              FROM (
                  SELECT id,
                         (created_at AT TIME ZONE 'America/Costa_Rica')::date AS pickup_date,
                         row_number() OVER (
                             PARTITION BY soda_id, (created_at AT TIME ZONE 'America/Costa_Rica')::date
                             ORDER BY created_at, id
                         ) AS pickup_number
                    FROM orders
              ) AS n
             WHERE n.id = o.id
            SQL);
        DB::statement(<<<'SQL'
            INSERT INTO pickup_counters (soda_id, pickup_date, last_number)
            SELECT soda_id, pickup_date, max(pickup_number) FROM orders GROUP BY soda_id, pickup_date
            SQL);
        DB::statement('ALTER TABLE pickup_counters FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE orders FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            ALTER TABLE orders
                ALTER COLUMN pickup_date SET NOT NULL,
                ALTER COLUMN pickup_number SET NOT NULL,
                ADD CONSTRAINT orders_pickup_number_check CHECK (pickup_number BETWEEN 1 AND 9999),
                ADD CONSTRAINT orders_soda_id_pickup_date_pickup_number_unique UNIQUE (soda_id, pickup_date, pickup_number)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE order_lines
                ALTER COLUMN preparation_minutes DROP DEFAULT,
                ADD CONSTRAINT order_lines_preparation_minutes_check CHECK (preparation_minutes BETWEEN 1 AND 120)
            SQL);
    }

    /** Remove the numbers, the counters and the frozen minutes. */
    public function down(): void
    {
        Schema::table('order_lines', function (Blueprint $table): void {
            $table->dropColumn('preparation_minutes');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['soda_id', 'pickup_date', 'pickup_number']);
            $table->dropColumn(['pickup_date', 'pickup_number']);
        });

        Schema::dropIfExists('pickup_counters');
    }
};
