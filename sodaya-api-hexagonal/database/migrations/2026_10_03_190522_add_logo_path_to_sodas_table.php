<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add the optional path of the logo of a soda. */
    public function up(): void
    {
        Schema::table('sodas', function (Blueprint $table): void {
            $table->string('logo_path', 255)->nullable();
        });
    }

    /** Remove the logo path. */
    public function down(): void
    {
        Schema::table('sodas', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });
    }
};
