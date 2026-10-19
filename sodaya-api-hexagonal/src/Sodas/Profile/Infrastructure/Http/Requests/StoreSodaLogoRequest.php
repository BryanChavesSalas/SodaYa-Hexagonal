<?php

declare(strict_types=1);

namespace Src\Sodas\Profile\Infrastructure\Http\Requests;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Src\Shared\Domain\Contracts\SodaContext;
use Src\Shared\Infrastructure\Http\Requests\ImageUpload;
use Src\Sodas\Profile\Application\DTOs\ChangeSodaLogoCommand;

#[SchemaName('LogoDeLaSoda')]
final class StoreSodaLogoRequest extends FormRequest
{
    /**
     * Validation rules for the logo of the soda.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['logo' => ImageUpload::rules()];
    }

    /** Map the validated logo to the use case input. */
    public function toCommand(SodaContext $sodaContext): ChangeSodaLogoCommand
    {
        $logo = $this->validated('logo');
        assert($logo instanceof UploadedFile);

        return new ChangeSodaLogoCommand(
            $sodaContext->current()->value,
            $logo->getPathname(),
            ImageUpload::extensionOf($logo),
        );
    }
}
