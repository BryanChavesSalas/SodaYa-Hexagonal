<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Application\DTOs;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * @phpstan-type MenuDishRow array{id: string, name: string, description: string|null, price: int, preparation_minutes: int, updated_at: string, photo_path: string|null}
 */
final readonly class MenuDish
{
    /** Describe a dish as a visitor sees it. */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public int $currentPrice,
        public int $preparationMinutes,
        public int $availablePortions,
        public DateTimeImmutable $updatedAt,
        public ?string $photoPath = null,
    ) {}

    /**
     * Rebuild the dish from its cached row; the portions are not cached, whoever rebuilds it reads them live.
     *
     * @param  MenuDishRow  $row
     */
    public static function fromArray(array $row, int $availablePortions): self
    {
        return new self(
            $row['id'],
            $row['name'],
            $row['description'],
            $row['price'],
            $row['preparation_minutes'],
            $availablePortions,
            new DateTimeImmutable($row['updated_at']),
            $row['photo_path'],
        );
    }

    /** Tell whether no portions are left to sell. */
    public function isSoldOut(): bool
    {
        return $this->availablePortions === 0;
    }

    /**
     * Flatten what only changes when the owner edits: the portions change with every order and stay out.
     *
     * @return MenuDishRow
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->currentPrice,
            'preparation_minutes' => $this->preparationMinutes,
            'updated_at' => $this->updatedAt->format(DateTimeInterface::ATOM),
            'photo_path' => $this->photoPath,
        ];
    }
}
