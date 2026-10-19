<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create the order lines, tied to their order and to a dish of the same soda. */
    public function up(): void
    {
        DB::statement('ALTER TABLE dishes ADD CONSTRAINT dishes_id_soda_id_unique UNIQUE (id, soda_id)');

        Schema::create('order_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->uuid('order_id');
            $table->foreignUuid('soda_id')->constrained()->cascadeOnDelete();
            $table->uuid('dish_id');
            $table->string('dish_name', 120);
            $table->integer('unit_price');
            $table->smallInteger('quantity');

            $table->unique(['order_id', 'dish_id']);
            $table->index(['soda_id', 'dish_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE order_lines
                ADD CONSTRAINT order_lines_unit_price_check CHECK (unit_price BETWEEN 100 AND 100000),
                ADD CONSTRAINT order_lines_quantity_check CHECK (quantity BETWEEN 1 AND 20),
                ADD CONSTRAINT order_lines_order_same_soda_foreign
                    FOREIGN KEY (order_id, soda_id) REFERENCES orders (id, soda_id) ON DELETE CASCADE,
                ADD CONSTRAINT order_lines_dish_same_soda_foreign
                    FOREIGN KEY (dish_id, soda_id) REFERENCES dishes (id, soda_id) ON DELETE RESTRICT
            SQL);

        DB::statement('ALTER TABLE order_lines ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE order_lines FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY order_lines_soda_isolation ON order_lines
                USING (soda_id = NULLIF(current_setting('app.soda_id', true), '')::uuid)
                WITH CHECK (soda_id = NULLIF(current_setting('app.soda_id', true), '')::uuid)
            SQL);
    }

    /** Drop the order lines and the key they needed on dishes. */
    public function down(): void
    {
        Schema::dropIfExists('order_lines');
        DB::statement('ALTER TABLE dishes DROP CONSTRAINT dishes_id_soda_id_unique');
    }
};
