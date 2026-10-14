<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use Illuminate\Http\UploadedFile;

final class TestImages
{
    /** A real 1 × 1 grayscale JPEG. */
    private const string JPEG = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAAAP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AP//Z';

    /** A real 2 × 2 lossless WebP. */
    private const string WEBP = 'UklGRh4AAABXRUJQVlA4TBEAAAAvAUAAAAdQvCIXpf+BiOh/AAA=';

    /** Upload a real JPEG under the given client name. */
    public static function jpeg(string $name = 'foto.jpg'): UploadedFile
    {
        return self::upload($name, (string) base64_decode(self::JPEG, true));
    }

    /** Upload a real WebP under the given client name. */
    public static function webp(string $name = 'foto.webp'): UploadedFile
    {
        return self::upload($name, (string) base64_decode(self::WEBP, true));
    }

    /** Upload a real grayscale PNG; level 0 stores the pixels uncompressed, which makes big files on purpose. */
    public static function png(int $width = 2, int $height = 2, string $name = 'foto.png', int $compression = 9): UploadedFile
    {
        $pixels = str_repeat("\0" . str_repeat("\x80", $width), $height);
        $header = pack('NNCCCCC', $width, $height, 8, 0, 0, 0, 0);

        $png = "\x89PNG\r\n\x1a\n"
            . self::chunk('IHDR', $header)
            . self::chunk('IDAT', (string) gzcompress($pixels, $compression))
            . self::chunk('IEND', '');

        return self::upload($name, $png);
    }

    /** Upload plain text disguised with an image extension. */
    public static function textNamed(string $name): UploadedFile
    {
        return self::upload($name, 'esto no es una imagen');
    }

    /**
     * Write the bytes to a temporary file and upload it under the client name. Unlike UploadedFile::fake(),
     * a real upload detects its type from the content, as it happens with a browser.
     */
    private static function upload(string $name, string $content): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, $content);
        register_shutdown_function(fn() => @unlink($path));

        return new UploadedFile($path, $name, test: true);
    }

    /** Build a PNG chunk: length, type, data and checksum. */
    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }
}
