<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create the orders table with its states, its keys and its isolation policy. */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('soda_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('cancellation_origin', 30)->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'soda_id']);
            $table->index('customer_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE orders
                ADD CONSTRAINT orders_status_check
                    CHECK (status IN ('pending', 'confirmed', 'ready', 'delivered', 'cancelled')),
                ADD CONSTRAINT orders_cancellation_origin_check
                    CHECK (cancellation_origin IN ('customer', 'kitchen', 'payment_timeout', 'confirmation_timeout')),
                ADD CONSTRAINT orders_cancellation_check
                    CHECK ((status = 'cancelled') = (cancellation_origin IS NOT NULL AND cancelled_at IS NOT NULL))
            SQL);

        DB::statement('ALTER TABLE orders ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE orders FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY orders_soda_isolation ON orders
                USING (soda_id = NULLIF(current_setting('app.soda_id', true), '')::uuid)
                WITH CHECK (soda_id = NULLIF(current_setting('app.soda_id', true), '')::uuid)
            SQL);
    }

    /** Drop the orders table with its policy. */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
