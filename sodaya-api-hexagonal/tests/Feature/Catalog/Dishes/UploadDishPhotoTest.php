<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog\Dishes;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\TestImages;
use Tests\TestCase;

final class UploadDishPhotoTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private DishModel $dish;

    /** Create the dish of the current soda and a temporary media disk. */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actOnNewSoda();
        $this->dish = DishModel::factory()->create(['soda_id' => $this->soda->id]);
    }

    /** JPG, PNG and WebP are stored with the extension of their content and a generated name. */
    #[DataProvider('acceptedImages')]
    public function test_owner_uploads_an_accepted_image(string $factory, string $extension): void
    {
        $response = $this->post($this->endpoint(), ['foto' => TestImages::$factory(name: 'vitrina.gif')], ['Accept' => 'application/json'])
            ->assertOk();

        $path = (string) DishModel::query()->findOrFail($this->dish->id)->photo_path;

        $this->assertMatchesRegularExpression("#^sodas/{$this->soda->id}/platos/[0-9a-f-]{36}\\.{$extension}$#", $path);
        $this->assertStringNotContainsString('vitrina', $path);
        Storage::disk('public')->assertExists($path);
        $response->assertJsonPath('data.foto', "/storage/{$path}");
    }

    /**
     * The three image types and the extension the server gives them.
     *
     * @return array<string, array{string, string}>
     */
    public static function acceptedImages(): array
    {
        return [
            'jpeg' => ['jpeg', 'jpg'],
            'png' => ['png', 'png'],
            'webp' => ['webp', 'webp'],
        ];
    }

    /** A file is judged by its content: text with an image extension is rejected. */
    public function test_content_that_is_not_an_image_is_rejected(): void
    {
        $this->postPhoto(TestImages::textNamed('foto.jpg'))
            ->assertUnprocessable()
            ->assertJsonPath('type', 'http://localhost/problemas/datos-invalidos')
            ->assertJsonStructure(['errores' => ['foto']]);
    }

    /** A name with a PHP extension is rejected even when the content is an image. */
    public function test_php_file_name_is_rejected(): void
    {
        $this->postPhoto(TestImages::jpeg('foto.php'))
            ->assertUnprocessable()
            ->assertJsonStructure(['errores' => ['foto']]);
    }

    /** An image over 2 MB is rejected. */
    public function test_image_heavier_than_two_megabytes_is_rejected(): void
    {
        $this->postPhoto(TestImages::png(1500, 1500, compression: 0))
            ->assertUnprocessable()
            ->assertJsonStructure(['errores' => ['foto']]);
    }

    /** An image wider or taller than 4000 pixels is rejected, and 4000 is accepted. */
    public function test_image_larger_than_four_thousand_pixels_is_rejected(): void
    {
        $this->postPhoto(TestImages::png(4001, 1))->assertUnprocessable()->assertJsonStructure(['errores' => ['foto']]);
        $this->postPhoto(TestImages::png(1, 4001))->assertUnprocessable()->assertJsonStructure(['errores' => ['foto']]);
        $this->postPhoto(TestImages::png(4000, 1))->assertOk();
    }

    /** A new photo replaces the previous one, which is deleted. */
    public function test_new_photo_deletes_the_previous_one(): void
    {
        $this->postPhoto(TestImages::jpeg())->assertOk();
        $first = (string) DishModel::query()->findOrFail($this->dish->id)->photo_path;

        $this->postPhoto(TestImages::png())->assertOk();
        $second = (string) DishModel::query()->findOrFail($this->dish->id)->photo_path;

        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    /** A missing file is rejected. */
    public function test_photo_is_required(): void
    {
        $this->post($this->endpoint(), [], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errores' => ['foto']]);
    }

    /** Send a photo for the dish of the current soda. */
    private function postPhoto(UploadedFile $photo): TestResponse
    {
        return $this->post($this->endpoint(), ['foto' => $photo], ['Accept' => 'application/json']);
    }

    /** Address of the photo of the dish. */
    private function endpoint(): string
    {
        return "/api/v1/cocina/platos/{$this->dish->id}/foto";
    }
}
