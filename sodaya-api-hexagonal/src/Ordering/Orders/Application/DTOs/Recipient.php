<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\DTOs;

final readonly class Recipient
{
    /** Carry the email and the name a notice is addressed to. */
    public function __construct(
        public string $email,
        public string $name,
    ) {}
}
