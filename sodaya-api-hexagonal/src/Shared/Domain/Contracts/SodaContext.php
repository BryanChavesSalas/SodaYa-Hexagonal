<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Contracts;

use Src\Shared\Domain\ValueObjects\SodaId;

interface SodaContext
{
    /** Identify the soda the current operation belongs to. */
    public function current(): SodaId;
}
