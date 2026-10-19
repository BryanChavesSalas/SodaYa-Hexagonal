<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use Src\Shared\Domain\Contracts\ImageStorage;

final class FakeImageStorage implements ImageStorage
{
    /** @var list<string> */
    public private(set) array $stored = [];

    /** @var list<string> */
    public private(set) array $deleted = [];

    private int $sequence = 0;

    /** Remember the image under a predictable name. */
    public function store(string $directory, string $sourcePath, string $extension): string
    {
        $path = "{$directory}/imagen-".++$this->sequence.".{$extension}";
        $this->stored[] = $path;

        return $path;
    }

    /** Remember that the image was deleted. */
    public function delete(string $path): void
    {
        $this->deleted[] = $path;
    }

    /** Build a fake public address. */
    public function url(string $path): string
    {
        return "https://imagenes.test/{$path}";
    }
}
