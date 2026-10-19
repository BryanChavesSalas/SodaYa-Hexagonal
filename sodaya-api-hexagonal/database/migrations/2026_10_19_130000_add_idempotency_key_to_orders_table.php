<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remember the key a customer sent with an order and a fingerprint of what the order asked for,
     * so a repeated request is recognized and the same key with other content is refused.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('idempotency_key', 100)->nullable();
            $table->char('request_fingerprint', 64)->nullable();

            $table->unique(['customer_id', 'idempotency_key']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE orders
                ADD CONSTRAINT orders_idempotency_check
                    CHECK ((idempotency_key IS NULL) = (request_fingerprint IS NULL))
            SQL);
    }

    /** Forget the keys. */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['customer_id', 'idempotency_key']);
            $table->dropColumn(['idempotency_key', 'request_fingerprint']);
        });
    }
};
