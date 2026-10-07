<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create the exceptional closures table. */
    public function up(): void
    {
        Schema::create('exceptional_closures', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('soda_id')->constrained('sodas')->cascadeOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('reason', 255)->nullable();
            $table->timestampsTz();

            $table->index(['soda_id', 'starts_at', 'ends_at']);
        });

        DB::statement('ALTER TABLE exceptional_closures ADD CONSTRAINT exceptional_closures_valid_check CHECK (starts_at < ends_at)');
    }

    /** Drop the exceptional closures table. */
    public function down(): void
    {
        Schema::dropIfExists('exceptional_closures');
    }
};
