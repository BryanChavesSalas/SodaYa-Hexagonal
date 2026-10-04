<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create the tenants table. */
    public function up(): void
    {
        Schema::create('sodas', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->string('name', 120);
            $table->timestampsTz();
        });
    }

    /** Drop the tenants table. */
    public function down(): void
    {
        Schema::dropIfExists('sodas');
    }
};
