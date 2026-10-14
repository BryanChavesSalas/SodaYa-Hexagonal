<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Src\Shared\Domain\Contracts\ImageStorage;

final readonly class FilesystemImageStorage implements ImageStorage
{
    /** Copy the image to the media disk under a time-ordered UUID; the name sent by the browser is never used. */
    public function store(string $directory, string $sourcePath, string $extension): string
    {
        $path = trim($directory, '/') . '/' . Str::uuid7() . '.' . $extension;

        $stream = fopen($sourcePath, 'rb');
        $this->disk()->put($path, $stream === false ? '' : $stream);

        return $path;
    }

    /** Remove the image from the media disk. */
    public function delete(string $path): void
    {
        $this->disk()->delete($path);
    }

    /** Ask the media disk for the public address of the image. */
    public function url(string $path): string
    {
        return Storage::disk($this->diskName())->url($path);
    }

    /** Disk configured for the images of the sodas. */
    private function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    /** Name of the media disk, read from the configuration. */
    private function diskName(): string
    {
        $name = config('filesystems.media');

        return is_string($name) ? $name : 'public';
    }
}
