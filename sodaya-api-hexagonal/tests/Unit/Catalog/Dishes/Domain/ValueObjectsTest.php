<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Dishes\Domain;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Catalog\Dishes\Domain\ValueObjects\DishDescription;
use Src\Catalog\Dishes\Domain\ValueObjects\DishName;
use Src\Catalog\Dishes\Domain\ValueObjects\Portions;
use Src\Catalog\Dishes\Domain\ValueObjects\PreparationTime;
use Src\Catalog\Dishes\Domain\ValueObjects\Price;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final class ValueObjectsTest extends TestCase
{
    /** Values on the boundaries of each range are accepted. */
    public function test_accepts_boundary_values(): void
    {
        $this->assertSame(100, new Price(100)->amount);
        $this->assertSame(100_000, new Price(100_000)->amount);
        $this->assertSame(1, new PreparationTime(1)->minutes);
        $this->assertSame(120, new PreparationTime(120)->minutes);
        $this->assertSame(0, new Portions(0)->quantity);
        $this->assertSame(500, new Portions(500)->quantity);
        $this->assertSame(120, mb_strlen(new DishName(str_repeat('ñ', 120))->value));
        $this->assertSame(500, mb_strlen(new DishDescription(str_repeat('á', 500))->value));
    }

    /** Text values are stored without surrounding whitespace. */
    public function test_trims_text_values(): void
    {
        $this->assertSame('Casado de pollo', new DishName('  Casado de pollo ')->value);
        $this->assertSame('Con ensalada', new DishDescription(" Con ensalada\n")->value);
    }

    /** Out-of-range values are rejected with a translatable error. */
    #[DataProvider('invalidValues')]
    public function test_rejects_invalid_values(Closure $build, string $translationKey): void
    {
        try {
            $build();
            $this->fail('An invalid value was accepted.');
        } catch (InvalidValueException $exception) {
            $this->assertSame($translationKey, $exception->translationKey());
        }
    }

    /**
     * Builders of invalid value objects and the error each one raises.
     *
     * @return array<string, array{Closure, string}>
     */
    public static function invalidValues(): array
    {
        return [
            'price below minimum' => [fn () => new Price(99), 'catalog.price_out_of_range'],
            'price above maximum' => [fn () => new Price(100_001), 'catalog.price_out_of_range'],
            'no preparation time' => [fn () => new PreparationTime(0), 'catalog.preparation_time_out_of_range'],
            'preparation time above maximum' => [fn () => new PreparationTime(121), 'catalog.preparation_time_out_of_range'],
            'negative portions' => [fn () => new Portions(-1), 'catalog.portions_out_of_range'],
            'portions above maximum' => [fn () => new Portions(501), 'catalog.portions_out_of_range'],
            'blank name' => [fn () => new DishName('   '), 'catalog.name_invalid'],
            'name too long' => [fn () => new DishName(str_repeat('a', 121)), 'catalog.name_invalid'],
            'blank description' => [fn () => new DishDescription(''), 'catalog.description_invalid'],
            'description too long' => [fn () => new DishDescription(str_repeat('a', 501)), 'catalog.description_invalid'],
        ];
    }
}
