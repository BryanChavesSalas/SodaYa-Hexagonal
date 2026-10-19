<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    /** Spanish is the default locale with English as fallback. */
    public function test_default_locale_is_spanish(): void
    {
        $this->assertSame('es', app()->getLocale());
        $this->assertSame('en', app()->getFallbackLocale());
    }

    /** Validation failures are reported in Spanish. */
    public function test_validation_messages_are_in_spanish(): void
    {
        $validator = Validator::make(['precio' => null], ['precio' => 'required']);
        $this->assertSame('El campo precio es obligatorio.', $validator->errors()->first('precio'));
    }

    /** Every framework language line has a Spanish translation. */
    #[DataProvider('languageFiles')]
    public function test_spanish_translations_cover_every_framework_line(string $file): void
    {
        $english = array_filter(Arr::dot(require lang_path("en/{$file}.php")), is_string(...));
        $spanish = Arr::dot(require lang_path("es/{$file}.php"));
        $this->assertSame([], array_keys(array_diff_key($english, $spanish)));
    }

    /**
     * Language files published by the framework.
     *
     * @return array<string, array{string}>
     */
    public static function languageFiles(): array
    {
        return [
            'auth' => ['auth'],
            'pagination' => ['pagination'],
            'passwords' => ['passwords'],
            'validation' => ['validation'],
        ];
    }
}
