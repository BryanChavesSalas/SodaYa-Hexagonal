<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Dishes\Application;

use PHPUnit\Framework\TestCase;
use Src\Catalog\Dishes\Application\DTOs\ChangeDishPhotoCommand;
use Src\Catalog\Dishes\Application\DTOs\CreateDishCommand;
use Src\Catalog\Dishes\Application\UseCases\ChangeDishPhoto;
use Src\Catalog\Dishes\Application\UseCases\CreateDish;
use Src\Catalog\Dishes\Domain\Entities\Dish;
use Src\Catalog\Dishes\Domain\Exceptions\DishNotFoundException;
use Tests\Support\Catalog\InMemoryDishRepository;
use Tests\Support\Shared\FakeImageStorage;

final class ChangeDishPhotoTest extends TestCase
{
    private const string SODA_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22';

    private const string OTHER_SODA_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d23';

    private InMemoryDishRepository $dishes;

    private FakeImageStorage $images;

    private ChangeDishPhoto $changeDishPhoto;

    /** Wire the use case to in-memory adapters. */
    protected function setUp(): void
    {
        $this->dishes = new InMemoryDishRepository;
        $this->images = new FakeImageStorage;
        $this->changeDishPhoto = new ChangeDishPhoto($this->dishes, $this->images);
    }

    /** The photo is stored in the folder of the soda and the dish keeps its path. */
    public function test_stores_the_photo_in_the_folder_of_the_soda(): void
    {
        $dish = $this->existingDish();

        $changed = $this->changeDishPhoto->execute($this->command($dish->id->value, 'png'));

        $this->assertSame('sodas/'.self::SODA_ID.'/platos/imagen-1.png', $changed->photoPath);
        $this->assertSame([$changed->photoPath], $this->images->stored);
        $this->assertSame([], $this->images->deleted);
    }

    /** A second photo deletes the first one. */
    public function test_replacing_the_photo_deletes_the_previous_one(): void
    {
        $dish = $this->existingDish();
        $first = $this->changeDishPhoto->execute($this->command($dish->id->value, 'jpg'))->photoPath;

        $this->changeDishPhoto->execute($this->command($dish->id->value, 'webp'));

        $this->assertSame([$first], $this->images->deleted);
    }

    /** A dish of another soda is not found and nothing is stored. */
    public function test_dish_of_another_soda_is_not_found(): void
    {
        $dish = $this->existingDish();

        try {
            $this->changeDishPhoto->execute(new ChangeDishPhotoCommand($dish->id->value, self::OTHER_SODA_ID, '/tmp/foto', 'jpg'));
            $this->fail('A dish of another soda was found.');
        } catch (DishNotFoundException) {
            $this->assertSame([], $this->images->stored);
        }
    }

    /** Build the command for a dish of the soda. */
    private function command(string $dishId, string $extension): ChangeDishPhotoCommand
    {
        return new ChangeDishPhotoCommand($dishId, self::SODA_ID, '/tmp/foto', $extension);
    }

    /** Create a dish of the soda. */
    private function existingDish(): Dish
    {
        return new CreateDish($this->dishes)->execute(
            new CreateDishCommand(self::SODA_ID, 'Casado de pollo', null, 2_800, 15, 10, null),
        );
    }
}
