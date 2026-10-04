<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create the dish categories table. */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('soda_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->timestampsTz();

            $table->unique(['soda_id', 'name']);
            $table->unique(['id', 'soda_id']);
        });
    }

    /** Drop the dish categories table. */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
