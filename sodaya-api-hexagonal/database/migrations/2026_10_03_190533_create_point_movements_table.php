<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep the points of each customer as a ledger: rows are only added, the balance is their sum,
     * and a purchase earns points once per order.
     */
    public function up(): void
    {
        Schema::create('point_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->string('reason', 20);
            $table->integer('points');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['customer_id', 'created_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE point_movements
                ADD CONSTRAINT point_movements_reason_check CHECK (reason IN ('purchase', 'redemption')),
                ADD CONSTRAINT point_movements_points_check CHECK (
                    (reason = 'purchase' AND points > 0 AND order_id IS NOT NULL)
                    OR (reason = 'redemption' AND points < 0)
                )
            SQL);

        DB::statement("CREATE UNIQUE INDEX point_movements_purchase_order_unique ON point_movements (order_id) WHERE reason = 'purchase'");

        DB::statement("REVOKE UPDATE, DELETE, TRUNCATE ON point_movements FROM {$this->applicationRole()}");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_reject_point_movement_change() RETURNS trigger
                LANGUAGE plpgsql
                SECURITY INVOKER
                SET search_path = public
            AS $$
            BEGIN
                RAISE EXCEPTION 'Point movements cannot be changed or deleted; add a new movement instead.';
            END;
            $$;

            CREATE TRIGGER trg_point_movements_immutable
                BEFORE UPDATE OR DELETE ON point_movements
                FOR EACH ROW
                EXECUTE FUNCTION fn_reject_point_movement_change();
            SQL);
    }

    /** Drop the ledger, its trigger and its function. */
    public function down(): void
    {
        Schema::dropIfExists('point_movements');
        DB::statement('DROP FUNCTION fn_reject_point_movement_change()');
    }

    /** Role the application connects with, quoted as an identifier. */
    private function applicationRole(): string
    {
        $name = config('database.connections.pgsql.username');

        return '"'.str_replace('"', '""', is_string($name) ? $name : '').'"';
    }
};
