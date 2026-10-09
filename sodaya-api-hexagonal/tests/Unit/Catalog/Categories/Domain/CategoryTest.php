<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Categories\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Catalog\Categories\Domain\Entities\Category;
use Src\Catalog\Categories\Domain\ValueObjects\CategoryName;
use Src\Catalog\Shared\Domain\ValueObjects\CategoryId;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\SodaId;

final class CategoryTest extends TestCase
{
    /** A category is created with its identity, soda and name. */
    public function test_category_is_created_with_its_state(): void
    {
        $category = $this->category();

        $this->assertSame(
            '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d11',
            $category->id->value,
        );

        $this->assertSame(
            '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22',
            $category->sodaId->value,
        );

        $this->assertSame('Bebidas', $category->name->value);
    }

    /** Renaming changes only the category name. */
    public function test_category_can_be_renamed(): void
    {
        $category = $this->category();

        $category->rename(new CategoryName('Bebidas frías'));

        $this->assertSame(
            '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d11',
            $category->id->value,
        );

        $this->assertSame(
            '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22',
            $category->sodaId->value,
        );

        $this->assertSame('Bebidas frías', $category->name->value);
    }

    /** The name is trimmed and may use the full length of the column. */
    public function test_name_is_trimmed_and_accepts_the_maximum_length(): void
    {
        $this->assertSame(
            'Bebidas',
            new CategoryName('  Bebidas  ')->value,
        );

        $this->assertSame(
            60,
            mb_strlen(new CategoryName(str_repeat('ñ', 60))->value),
        );
    }

    /** A blank or oversized name is rejected with a translatable error. */
    #[DataProvider('invalidNames')]
    public function test_rejects_invalid_names(string $name): void
    {
        try {
            new CategoryName($name);

            $this->fail('An invalid category name was accepted.');
        } catch (InvalidValueException $exception) {
            $this->assertSame(
                'catalog.category_name_invalid',
                $exception->translationKey(),
            );
        }
    }

    /**
     * Names that break the invariant of the value object.
     *
     * @return array<string, array{string}>
     */
    public static function invalidNames(): array
    {
        return [
            'blank name' => ['   '],
            'name too long' => [str_repeat('a', 61)],
        ];
    }

    /** Build a valid category for the scenarios. */
    private function category(): Category
    {
        return Category::create(
            new CategoryId('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d11'),
            new SodaId('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22'),
            new CategoryName('Bebidas'),
        );
    }
}
