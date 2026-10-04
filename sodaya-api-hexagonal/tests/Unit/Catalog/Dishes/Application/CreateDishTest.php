<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Dishes\Application;

use PHPUnit\Framework\TestCase;
use Src\Catalog\Dishes\Application\DTOs\CreateDishCommand;
use Src\Catalog\Dishes\Application\UseCases\CreateDish;
use Src\Catalog\Dishes\Domain\Exceptions\DishNameAlreadyInUseException;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\SodaId;
use Tests\Support\Catalog\InMemoryDishRepository;

final class CreateDishTest extends TestCase
{
    private const string SODA_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22';

    private InMemoryDishRepository $dishes;

    private CreateDish $createDish;

    /** Wire the use case to an in-memory repository. */
    protected function setUp(): void
    {
        $this->dishes = new InMemoryDishRepository;
        $this->createDish = new CreateDish($this->dishes);
    }

    /** A valid command stores an active dish for the soda. */
    public function test_creates_an_active_dish_for_the_soda(): void
    {
        $dish = $this->createDish->execute($this->command());

        $stored = $this->dishes->find($dish->id, new SodaId(self::SODA_ID));

        $this->assertSame($dish, $stored);
        $this->assertTrue($dish->active);
        $this->assertSame('Casado de pollo', $dish->name->value);
        $this->assertNull($dish->description);
        $this->assertNull($dish->categoryId);
    }

    /** Invalid data is rejected before anything is stored. */
    public function test_rejects_data_that_breaks_an_invariant(): void
    {
        try {
            $this->createDish->execute($this->command(price: 50));
            $this->fail('An out-of-range price was accepted.');
        } catch (InvalidValueException) {
            $this->assertSame([], $this->dishes->allOf(new SodaId(self::SODA_ID)));
        }
    }

    /** Two dishes of the same soda cannot share a name. */
    public function test_rejects_a_name_already_used_in_the_soda(): void
    {
        $this->createDish->execute($this->command());

        $this->expectException(DishNameAlreadyInUseException::class);

        $this->createDish->execute($this->command());
    }

    /** Build a valid command with an optional price override. */
    private function command(int $price = 2_800): CreateDishCommand
    {
        return new CreateDishCommand(self::SODA_ID, 'Casado de pollo', null, $price, 15, 10, null);
    }
}
