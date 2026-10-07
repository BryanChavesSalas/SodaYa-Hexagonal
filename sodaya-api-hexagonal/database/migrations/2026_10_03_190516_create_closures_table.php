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
        Schema::create('closures', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('soda_id')->constrained()->cascadeOnDelete();
            $table->date('closed_on');
            $table->string('reason', 200)->nullable();
            $table->timestampsTz();

            $table->unique(['soda_id', 'closed_on']);
        });
    }

    /** Drop the exceptional closures table. */
    public function down(): void
    {
        Schema::dropIfExists('closures');
    }
};
