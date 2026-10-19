<?php

declare(strict_types=1);

namespace Tests\Feature\Sodas\Profile;

use Illuminate\Support\Facades\Storage;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\TestImages;
use Tests\TestCase;

final class UploadSodaLogoTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/cocina/logo';

    /** Authenticate the owner of a soda and use a temporary media disk. */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actOnNewSoda();
    }

    /** The logo is stored with a generated name in the folder of the soda and the profile shows it. */
    public function test_owner_uploads_the_logo_of_the_soda(): void
    {
        $response = $this->post(self::ENDPOINT, ['logo' => TestImages::webp('mi-logo.png')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.id', $this->soda->id)
            ->assertJsonPath('data.nombre', $this->soda->name);

        $path = (string) SodaModel::query()->findOrFail($this->soda->id)->logo_path;

        $this->assertMatchesRegularExpression("#^sodas/{$this->soda->id}/logo/[0-9a-f-]{36}\\.webp$#", $path);
        Storage::disk('public')->assertExists($path);
        $response->assertJsonPath('data.logo', "/storage/{$path}");
    }

    /** The logo always belongs to the soda of the token, even if the request names another. */
    public function test_logo_goes_to_the_soda_of_the_token(): void
    {
        $other = SodaModel::factory()->create();

        $this->post(self::ENDPOINT, ['logo' => TestImages::png(), 'soda_id' => $other->id], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.id', $this->soda->id);

        $this->assertNull(SodaModel::query()->findOrFail($other->id)->logo_path);
    }

    /** A new logo replaces the previous one, which is deleted. */
    public function test_new_logo_deletes_the_previous_one(): void
    {
        $this->post(self::ENDPOINT, ['logo' => TestImages::png()], ['Accept' => 'application/json'])->assertOk();
        $first = (string) SodaModel::query()->findOrFail($this->soda->id)->logo_path;

        $this->post(self::ENDPOINT, ['logo' => TestImages::jpeg()], ['Accept' => 'application/json'])->assertOk();

        Storage::disk('public')->assertMissing($first);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    /** The logo follows the same rules as the photos. */
    public function test_content_that_is_not_an_image_is_rejected(): void
    {
        $this->post(self::ENDPOINT, ['logo' => TestImages::textNamed('logo.png')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errores' => ['logo']]);

        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
