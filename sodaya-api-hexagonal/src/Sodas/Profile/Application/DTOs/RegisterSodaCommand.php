<?php

declare(strict_types=1);

namespace Src\Sodas\Profile\Application\DTOs;

use SensitiveParameter;

final readonly class RegisterSodaCommand
{
    /** Carry the data needed to register a soda and its owner. */
    public function __construct(
        public string $name,
        public string $ownerName,
        public string $ownerEmail,
        #[SensitiveParameter] public string $ownerPassword,
        public ?string $paymentAccountId,
    ) {}
}
