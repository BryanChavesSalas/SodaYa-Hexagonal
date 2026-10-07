<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('closures table has required columns', function () {
    expect(Schema::hasTable('closures'))->toBeTrue()
        ->and(Schema::hasColumns('closures', ['id', 'soda_id', 'date', 'reason', 'created_at', 'updated_at']))->toBeTrue();
});
