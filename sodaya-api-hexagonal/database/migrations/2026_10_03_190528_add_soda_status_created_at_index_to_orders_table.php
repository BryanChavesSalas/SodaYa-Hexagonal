<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** CREATE INDEX CONCURRENTLY cannot run inside a transaction. */
    public $withinTransaction = false;

    /** Index the orders the way the kitchen and the reports read them: by soda, status and creation time. */
    public function up(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS orders_soda_id_status_created_at_index ON orders (soda_id, status, created_at)');
    }

    /** Drop the index without blocking the table. */
    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS orders_soda_id_status_created_at_index');
    }
};
