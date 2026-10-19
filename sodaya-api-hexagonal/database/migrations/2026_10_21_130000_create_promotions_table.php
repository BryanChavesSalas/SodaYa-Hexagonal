<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the happy hours: a discount for the whole soda on one day of the week, inside a window.
     * Two promotions of the same soda and day cannot share a minute; UNIQUE would not catch
     * 13:00-15:00 against 14:00-16:00, so the table excludes overlapping ranges with a GiST index.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('promotions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('soda_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->smallInteger('percentage');
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE promotions
                ADD CONSTRAINT promotions_weekday_check CHECK (weekday BETWEEN 1 AND 7),
                ADD CONSTRAINT promotions_starts_at_check CHECK (starts_at < ends_at),
                ADD CONSTRAINT promotions_percentage_check CHECK (percentage BETWEEN 1 AND 90),
                ADD CONSTRAINT promotions_no_overlap_excl EXCLUDE USING gist (
                    soda_id WITH =,
                    weekday WITH =,
                    timerange(starts_at, ends_at) WITH &&
                )
            SQL);

        DB::statement('ALTER TABLE promotions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE promotions FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY promotions_soda_isolation ON promotions
                USING (soda_id = NULLIF(current_setting('app.soda_id', true), '')::uuid)
                WITH CHECK (soda_id = NULLIF(current_setting('app.soda_id', true), '')::uuid)
            SQL);
    }

    /** Drop the happy hours with their policy and constraints; the extension stays for the schedules. */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
