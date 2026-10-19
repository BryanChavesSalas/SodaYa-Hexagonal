<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Keep a row for every status change of an order, written by a trigger the application cannot alter. */
    public function up(): void
    {
        Schema::create('order_status_history', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->uuid('order_id');
            $table->foreignUuid('soda_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->timestampTz('changed_at')->useCurrent();

            $table->index(['order_id', 'changed_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE order_status_history
                ADD CONSTRAINT order_status_history_order_same_soda_foreign
                    FOREIGN KEY (order_id, soda_id) REFERENCES orders (id, soda_id) ON DELETE CASCADE
            SQL);

        DB::statement('ALTER TABLE order_status_history ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE order_status_history FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY order_status_history_soda_isolation ON order_status_history
                USING (soda_id = NULLIF(current_setting('app.soda_id', true), '')::uuid)
                WITH CHECK (soda_id = NULLIF(current_setting('app.soda_id', true), '')::uuid)
            SQL);

        DB::statement("REVOKE UPDATE, DELETE, TRUNCATE ON order_status_history FROM {$this->applicationRole()}");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_record_order_status_change() RETURNS trigger
                LANGUAGE plpgsql
                SECURITY INVOKER
                SET search_path = public
            AS $$
            BEGIN
                INSERT INTO order_status_history (order_id, soda_id, from_status, to_status, changed_at)
                VALUES (NEW.id, NEW.soda_id, OLD.status, NEW.status, now());

                RETURN NULL;
            END;
            $$;

            CREATE TRIGGER trg_orders_status_history
                AFTER UPDATE OF status ON orders
                FOR EACH ROW
                WHEN (OLD.status IS DISTINCT FROM NEW.status)
                EXECUTE FUNCTION fn_record_order_status_change();
            SQL);
    }

    /** Drop the trigger, its function and the history. */
    public function down(): void
    {
        DB::statement('DROP TRIGGER trg_orders_status_history ON orders');
        DB::statement('DROP FUNCTION fn_record_order_status_change()');
        Schema::dropIfExists('order_status_history');
    }

    /** Role the application connects with, quoted as an identifier. */
    private function applicationRole(): string
    {
        $name = config('database.connections.pgsql.username');

        return '"'.str_replace('"', '""', is_string($name) ? $name : '').'"';
    }
};
