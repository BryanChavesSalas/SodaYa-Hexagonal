<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep every attempt to pay an order: the provider, its reference, the amount in colones and the outcome.
     * A partial unique index lets an order have at most one pending or approved payment, so a second attempt
     * answers the first instead of charging twice; a rejected one leaves room for another.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->uuid('order_id');
            $table->foreignUuid('soda_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('users')->restrictOnDelete();
            $table->string('provider', 20);
            $table->string('reference', 255)->nullable();
            $table->integer('amount');
            $table->string('status', 20)->default('pending');
            $table->string('decline_code', 60)->nullable();
            $table->string('idempotency_key', 100);
            $table->timestampTz('settled_at')->nullable();
            $table->timestampsTz();

            $table->unique('idempotency_key');
            $table->unique(['provider', 'reference']);
            $table->index(['order_id', 'created_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE payments
                ADD CONSTRAINT payments_order_same_soda_foreign
                    FOREIGN KEY (order_id, soda_id) REFERENCES orders (id, soda_id) ON DELETE CASCADE,
                ADD CONSTRAINT payments_amount_check CHECK (amount > 0),
                ADD CONSTRAINT payments_status_check CHECK (status IN ('pending', 'approved', 'rejected')),
                ADD CONSTRAINT payments_settled_at_check CHECK ((status = 'pending') = (settled_at IS NULL)),
                ADD CONSTRAINT payments_decline_code_check CHECK (status = 'rejected' OR decline_code IS NULL)
            SQL);

        DB::statement("CREATE UNIQUE INDEX payments_live_order_unique ON payments (order_id) WHERE status IN ('pending', 'approved')");

        DB::statement('ALTER TABLE payments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE payments FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY payments_soda_isolation ON payments
                USING (soda_id = NULLIF(current_setting('app.soda_id', true), '')::uuid)
                WITH CHECK (soda_id = NULLIF(current_setting('app.soda_id', true), '')::uuid)
            SQL);
    }

    /** Drop the payments with their policy and indexes. */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
