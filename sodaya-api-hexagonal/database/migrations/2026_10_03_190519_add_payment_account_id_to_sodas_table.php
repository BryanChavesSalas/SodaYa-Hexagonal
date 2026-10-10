<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add the payment account to the sodas table. */
    public function up(): void
    {
        Schema::table('sodas', function (Blueprint $table): void {
            $table->string('payment_account_id', 255)->nullable();
        });
    }

    /** Remove the payment account from the sodas table. */
    public function down(): void
    {
        Schema::table('sodas', function (Blueprint $table): void {
            $table->dropColumn('payment_account_id');
        });
    }
};
