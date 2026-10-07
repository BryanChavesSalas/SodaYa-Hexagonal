<?php

declare(strict_types=1);

namespace Src\Identity\Domain\Enums;

enum Role: string
{
    case Customer = 'cliente';
    case Kitchen = 'cocina';
    case Owner = 'dueno';

    /**
     * Abilities granted to the role: customers place orders, kitchen staff
     * run the kitchen, and the owner runs the kitchen and administers the soda.
     *
     * @return list<Ability>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::Customer => [Ability::PlaceOrders],
            self::Kitchen => [Ability::OperateKitchen],
            self::Owner => [Ability::OperateKitchen, Ability::Administer],
        };
    }

    /**
     * Abilities as the plain strings stored in the access token.
     *
     * @return list<string>
     */
    public function tokenAbilities(): array
    {
        return array_map(static fn (Ability $ability): string => $ability->value, $this->abilities());
    }

    /** Tell whether the role operates inside a single soda. */
    public function belongsToSoda(): bool
    {
        return $this !== self::Customer;
    }
}
