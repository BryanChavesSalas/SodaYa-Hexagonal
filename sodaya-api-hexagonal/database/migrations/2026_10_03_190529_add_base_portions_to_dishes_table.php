<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the portions a soda prepares of each dish on a normal day.
     * Existing dishes start with their current portions as their base.
     */
    public function up(): void
    {
        Schema::table('dishes', function (Blueprint $table): void {
            $table->smallInteger('base_portions')->default(0);
        });

        DB::statement('ALTER TABLE dishes ADD CONSTRAINT dishes_base_portions_check CHECK (base_portions BETWEEN 0 AND 500)');

        // The owner is subject to the policy too; lifting FORCE inside this transaction lets the backfill reach every soda.
        DB::statement('ALTER TABLE dishes NO FORCE ROW LEVEL SECURITY');
        DB::statement('UPDATE dishes SET base_portions = available_portions');
        DB::statement('ALTER TABLE dishes FORCE ROW LEVEL SECURITY');
    }

    /** Remove the base portions. */
    public function down(): void
    {
        Schema::table('dishes', function (Blueprint $table): void {
            $table->dropColumn('base_portions');
        });
    }
};
