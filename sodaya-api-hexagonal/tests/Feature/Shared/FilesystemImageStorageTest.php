<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Src\Shared\Infrastructure\Storage\FilesystemImageStorage;
use Tests\Support\Shared\TestImages;
use Tests\TestCase;

final class FilesystemImageStorageTest extends TestCase
{
    /** Replace the media disk with a temporary one. */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /** The stored file gets a UUID v7 name and keeps only the given extension. */
    public function test_stores_the_image_under_a_generated_name(): void
    {
        $upload = TestImages::jpeg('../../mi foto.php.jpg');

        $path = new FilesystemImageStorage()->store('sodas/abc/platos', $upload->getPathname(), 'jpg');

        $this->assertMatchesRegularExpression('#^sodas/abc/platos/[0-9a-f-]{36}\.jpg$#', $path);
        $this->assertTrue(Str::isUuid(basename($path, '.jpg'), 7));
        $this->assertSame((string) file_get_contents($upload->getPathname()), Storage::disk('public')->get($path));
    }

    /** Two uploads of the same file never share a name. */
    public function test_every_image_gets_its_own_name(): void
    {
        $upload = TestImages::png();
        $storage = new FilesystemImageStorage;

        $this->assertNotSame(
            $storage->store('logos', $upload->getPathname(), 'png'),
            $storage->store('logos', $upload->getPathname(), 'png'),
        );
    }

    /** Deleting removes the file and ignores a missing one. */
    public function test_deletes_an_image(): void
    {
        $upload = TestImages::webp();
        $storage = new FilesystemImageStorage;
        $path = $storage->store('logos', $upload->getPathname(), 'webp');

        $storage->delete($path);
        $storage->delete($path);

        Storage::disk('public')->assertMissing($path);
    }

    /** The public address comes from the configured disk. */
    public function test_builds_the_public_address(): void
    {
        $this->assertSame('/storage/logos/a.png', new FilesystemImageStorage()->url('logos/a.png'));
    }
}
