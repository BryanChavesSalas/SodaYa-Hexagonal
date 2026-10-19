<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep the moment of the last refresh of each materialized view: PostgreSQL does not record it anywhere.
     * Only the maintenance role, which refreshes, writes the row; the application role just reads it.
     */
    public function up(): void
    {
        Schema::create('materialized_view_refreshes', function (Blueprint $table): void {
            $table->string('view_name', 63)->primary();
            $table->timestampTz('refreshed_at');
        });

        DB::statement("REVOKE INSERT, UPDATE, DELETE, TRUNCATE ON materialized_view_refreshes FROM {$this->role('pgsql')}");
        DB::statement("GRANT SELECT, INSERT, UPDATE ON materialized_view_refreshes TO {$this->role('pgsql_maintenance')}");
    }

    /** Drop the table; the privileges go with it. */
    public function down(): void
    {
        Schema::dropIfExists('materialized_view_refreshes');
    }

    /** Role a connection logs in with, quoted as an identifier. */
    private function role(string $connection): string
    {
        $name = config("database.connections.{$connection}.username");

        return '"'.str_replace('"', '""', is_string($name) ? $name : '').'"';
    }
};
