<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create the schedules table with its integrity constraints. */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'timerange') THEN
                    CREATE TYPE timerange AS RANGE (subtype = time);
                END IF;
            END
            $$
            SQL);

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
                ADD CONSTRAINT schedules_opens_before_closes_check CHECK (opens_at < closes_at),
                ADD CONSTRAINT schedules_no_overlap_exclude EXCLUDE USING gist (
                    soda_id WITH =,
                    day_of_week WITH =,
                    timerange(opens_at, closes_at, '[)') WITH &&
                )
            SQL);
    }

    /** Drop the schedules table and the range type, unless something else still uses it. */
    public function down(): void
    {
        Schema::dropIfExists('schedules');

        DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                DROP TYPE IF EXISTS timerange;
            EXCEPTION
                WHEN dependent_objects_still_exist THEN NULL;
            END
            $$
            SQL);
    }
};
