<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Http\Requests;

use Illuminate\Http\UploadedFile;

final class ImageUpload
{
    /**
     * Image types read from the content of the file, with the extension the server gives them.
     *
     * @var array<string, string>
     */
    public const array TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public const int MAX_KILOBYTES = 2048;

    public const int MAX_PIXELS = 4000;

    /**
     * Rules for an uploaded image: real image content, weight and size.
     *
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
            'required',
            'file',
            'mimetypes:'.implode(',', array_keys(self::TYPES)),
            'max:'.self::MAX_KILOBYTES,
            'dimensions:max_width='.self::MAX_PIXELS.',max_height='.self::MAX_PIXELS,
        ];
    }

    /** Extension that matches the content of a validated image, never the one in its name. */
    public static function extensionOf(UploadedFile $image): string
    {
        return self::TYPES[(string) $image->getMimeType()];
    }
}
