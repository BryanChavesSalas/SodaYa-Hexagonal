<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Application\DTOs;

use DateTimeImmutable;
use DateTimeInterface;
use Src\Shared\Domain\ValueObjects\Money;

/**
 * @phpstan-type MenuDishRow array{id: string, name: string, description: string|null, price: int, preparation_minutes: int, updated_at: string, photo_path: string|null}
 */
final readonly class MenuDish
{
    /** Price the dish sells for right now: the base price with the active happy hour taken off. */
    public int $currentPrice;

    /** Describe a dish as a visitor sees it; until a discount is applied, the current price is the base price. */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public int $price,
        public int $preparationMinutes,
        public int $availablePortions,
        public DateTimeImmutable $updatedAt,
        public ?string $photoPath = null,
        ?int $currentPrice = null,
    ) {
        $this->currentPrice = $currentPrice ?? $price;
    }

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

    /** Return the same dish with the percentage of the happy hour taken off its base price. */
    public function withDiscount(int $percentage): self
    {
        return new self(
            $this->id,
            $this->name,
            $this->description,
            $this->price,
            $this->preparationMinutes,
            $this->availablePortions,
            $this->updatedAt,
            $this->photoPath,
            Money::colones($this->price)->withDiscount($percentage)->colones,
        );
    }

    /**
     * Flatten what only changes when the owner edits: the portions change with every order and the current
     * price with the clock, so both stay out.
     *
     * @return MenuDishRow
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'preparation_minutes' => $this->preparationMinutes,
            'updated_at' => $this->updatedAt->format(DateTimeInterface::ATOM),
            'photo_path' => $this->photoPath,
        ];
    }
}
