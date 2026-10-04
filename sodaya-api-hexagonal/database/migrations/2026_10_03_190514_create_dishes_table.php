<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create the dishes table with its integrity constraints. */
    public function up(): void
    {
        Schema::create('dishes', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('soda_id')->constrained()->cascadeOnDelete();
            $table->uuid('category_id')->nullable();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->integer('price');
            $table->smallInteger('preparation_minutes');
            $table->smallInteger('available_portions');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['soda_id', 'name']);
            $table->index(['soda_id', 'is_active']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE dishes
                ADD CONSTRAINT dishes_price_check CHECK (price BETWEEN 100 AND 100000),
                ADD CONSTRAINT dishes_preparation_minutes_check CHECK (preparation_minutes BETWEEN 1 AND 120),
                ADD CONSTRAINT dishes_available_portions_check CHECK (available_portions BETWEEN 0 AND 500),
                ADD CONSTRAINT dishes_category_same_soda_foreign
                    FOREIGN KEY (category_id, soda_id) REFERENCES categories (id, soda_id)
                    ON DELETE SET NULL (category_id)
            SQL);
    }

    /** Drop the dishes table. */
    public function down(): void
    {
        Schema::dropIfExists('dishes');
    }
};
