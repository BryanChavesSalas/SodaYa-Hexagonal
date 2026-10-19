<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Infrastructure\Http\Requests;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Src\Catalog\Dishes\Application\DTOs\ChangeDishPhotoCommand;
use Src\Shared\Domain\Contracts\SodaContext;
use Src\Shared\Infrastructure\Http\Requests\ImageUpload;

#[SchemaName('FotoDelPlato')]
final class StoreDishPhotoRequest extends FormRequest
{
    /**
     * Validation rules for the photo of a dish.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['foto' => ImageUpload::rules()];
    }

    /** Map the validated photo to the use case input. */
    public function toCommand(SodaContext $sodaContext): ChangeDishPhotoCommand
    {
        $photo = $this->validated('foto');
        assert($photo instanceof UploadedFile);

        return new ChangeDishPhotoCommand(
            (string) $this->route('plato'),
            $sodaContext->current()->value,
            $photo->getPathname(),
            ImageUpload::extensionOf($photo),
        );
    }
}
