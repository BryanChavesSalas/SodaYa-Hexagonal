<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Src\Catalog\Categories\Infrastructure\Persistence\Models\CategoryModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class JsonEncodingTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    /** Accents travel as UTF-8 and slashes without a backslash, in data and in problem documents. */
    public function test_json_keeps_accents_and_slashes_as_they_are(): void
    {
        $this->actOnNewSoda();
        CategoryModel::factory()->create(['soda_id' => $this->soda->id, 'name' => 'Cafetería']);

        $listing = (string) $this->getJson('/api/v1/cocina/categorias')->assertOk()->getContent();
        $problem = (string) $this->getJson('/api/v1/cocina/categorias/0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99')->getContent();

        $this->assertStringContainsString('"Cafetería"', $listing);
        $this->assertStringNotContainsString('\\u00ed', $listing);
        $this->assertStringContainsString('http://localhost/problemas/', $problem);
    }
}
