<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Tenancy;

use LogicException;
use Src\Shared\Domain\Contracts\SodaContext;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class ConfiguredSodaContext implements SodaContext
{
    /** Receive the soda identifier defined in the configuration. */
    public function __construct(private ?string $sodaId) {}

    /** Resolve the configured soda or fail on a missing setting. */
    public function current(): SodaId
    {
        if ($this->sodaId === null) {
            throw new LogicException('The default soda is not configured.');
        }

        return new SodaId($this->sodaId);
    }
}
