<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add the optional path of the photo of a dish. */
    public function up(): void
    {
        Schema::table('dishes', function (Blueprint $table): void {
            $table->string('photo_path', 255)->nullable();
        });
    }

    /** Remove the photo path. */
    public function down(): void
    {
        Schema::table('dishes', function (Blueprint $table): void {
            $table->dropColumn('photo_path');
        });
    }
};
