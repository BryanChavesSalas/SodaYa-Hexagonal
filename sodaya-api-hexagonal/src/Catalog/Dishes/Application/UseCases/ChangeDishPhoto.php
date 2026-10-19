<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Application\UseCases;

use Src\Catalog\Dishes\Application\DTOs\ChangeDishPhotoCommand;
use Src\Catalog\Dishes\Domain\Contracts\DishRepository;
use Src\Catalog\Dishes\Domain\Entities\Dish;
use Src\Catalog\Dishes\Domain\Exceptions\DishNotFoundException;
use Src\Catalog\Dishes\Domain\ValueObjects\DishId;
use Src\Shared\Domain\Contracts\ImageStorage;
use Src\Shared\Domain\ValueObjects\SodaId;
use Throwable;

final readonly class ChangeDishPhoto
{
    /** Receive the dish repository and the image storage ports. */
    public function __construct(
        private DishRepository $dishes,
        private ImageStorage $images,
    ) {}

    /** Store the new photo of a dish of the soda and delete the one it replaces. */
    public function execute(ChangeDishPhotoCommand $command): Dish
    {
        $dish = $this->dishes->find(new DishId($command->dishId), new SodaId($command->sodaId))
            ?? throw DishNotFoundException::create();

        $path = $this->images->store("sodas/{$command->sodaId}/platos", $command->imagePath, $command->extension);

        try {
            $previous = $dish->changePhoto($path);
            $this->dishes->save($dish);
        } catch (Throwable $exception) {
            $this->images->delete($path);

            throw $exception;
        }

        if ($previous !== null) {
            $this->images->delete($previous);
        }

        return $dish;
    }
}
