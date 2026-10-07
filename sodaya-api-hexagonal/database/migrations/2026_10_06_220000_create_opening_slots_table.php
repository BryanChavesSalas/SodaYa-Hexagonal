<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create the opening slots table. */
    public function up(): void
    {
        Schema::create('opening_slots', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('soda_id')->constrained('sodas')->cascadeOnDelete();
            $table->unsignedSmallInteger('day_of_week');
            $table->unsignedSmallInteger('opens_at');
            $table->unsignedSmallInteger('closes_at');
            $table->timestampsTz();

            $table->index('soda_id');
        });

        DB::statement('ALTER TABLE opening_slots ADD CONSTRAINT opening_slots_valid_check CHECK (day_of_week BETWEEN 1 AND 7 AND opens_at >= 0 AND closes_at <= 1440 AND opens_at < closes_at)');
    }

    /** Drop the opening slots table. */
    public function down(): void
    {
        Schema::dropIfExists('opening_slots');
    }
};
