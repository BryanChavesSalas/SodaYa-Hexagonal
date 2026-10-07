<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('soda_id')->constrained('sodas')->cascadeOnDelete();
            $table->date('date');
            $table->string('reason', 200)->nullable();
            $table->timestamps();

            $table->unique(['soda_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closures');
    }
};
