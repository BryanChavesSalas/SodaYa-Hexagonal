<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\ValueObjects;

enum Role: string
{
    case Customer = 'customer';
    case Kitchen = 'kitchen';
    case Owner = 'owner';

    /**
     * Abilities granted to the access tokens of the role.
     *
     * @return list<string>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::Customer => ['pedidos'],
            self::Kitchen => ['cocina'],
            self::Owner => ['cocina', 'administrar'],
        };
    }

    /** Tell whether the role belongs to the staff of a soda. */
    public function isStaff(): bool
    {
        return $this !== self::Customer;
    }
}
