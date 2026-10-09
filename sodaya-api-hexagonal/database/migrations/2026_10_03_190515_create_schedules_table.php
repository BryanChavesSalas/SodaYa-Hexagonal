<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create the opening hours table; slots of a soda cannot overlap within a day. */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement('DROP TYPE IF EXISTS timerange');
        DB::statement('CREATE TYPE timerange AS RANGE (subtype = time)');

        Schema::create('schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('soda_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('day_of_week');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE schedules
                ADD CONSTRAINT schedules_day_of_week_check CHECK (day_of_week BETWEEN 1 AND 7),
                ADD CONSTRAINT schedules_opens_at_check CHECK (opens_at < closes_at),
                ADD CONSTRAINT schedules_no_overlap_excl EXCLUDE USING gist (
                    soda_id WITH =,
                    day_of_week WITH =,
                    timerange(opens_at, closes_at) WITH &&
                )
            SQL);
    }

    /** Drop the opening hours table and the objects created for it. */
    public function down(): void
    {
        Schema::dropIfExists('schedules');

        DB::statement('DROP TYPE IF EXISTS timerange');
        DB::statement('DROP EXTENSION IF EXISTS btree_gist');
    }
};
