<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add the payment status of the order; the charge itself arrives with the payment module. */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('payment_status', 20)->default('pending');
            $table->timestampTz('paid_at')->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE orders
                ADD CONSTRAINT orders_payment_status_check CHECK (payment_status IN ('pending', 'approved')),
                ADD CONSTRAINT orders_paid_at_check CHECK ((payment_status = 'approved') = (paid_at IS NOT NULL))
            SQL);
    }

    /** Remove the payment status. */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['payment_status', 'paid_at']);
        });
    }
};
