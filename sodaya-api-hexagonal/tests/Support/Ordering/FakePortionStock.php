<?php

declare(strict_types=1);

namespace Tests\Support\Ordering;

use Src\Ordering\Orders\Application\Contracts\PortionStock;
use Src\Shared\Domain\ValueObjects\SodaId;

final class FakePortionStock implements PortionStock
{
    /** @var array<string, int> portions given back, by dish */
    public private(set) array $released = [];

    /** @var array<string, int> portions taken, by dish */
    public private(set) array $reserved = [];

    /** @param  array<string, int>  $available  portions left of each dish; a dish not listed has none */
    public function __construct(private array $available = []) {}

    /** Take the portions of every line if all of them are left, as the database function does. */
    public function reserve(SodaId $sodaId, array $lines): bool
    {
        foreach ($lines as $line) {
            if (($this->available[$line->dishId->value] ?? 0) < $line->quantity->value) {
                return false;
            }
        }

        foreach ($lines as $line) {
            $this->available[$line->dishId->value] -= $line->quantity->value;
            $this->reserved[$line->dishId->value] = ($this->reserved[$line->dishId->value] ?? 0) + $line->quantity->value;
        }

        return true;
    }

    /** Remember the portions given back. */
    public function release(SodaId $sodaId, array $lines): void
    {
        foreach ($lines as $line) {
            $this->released[$line->dishId->value] = ($this->released[$line->dishId->value] ?? 0) + $line->quantity->value;
        }
    }
}
