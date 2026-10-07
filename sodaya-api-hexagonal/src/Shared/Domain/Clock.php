<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

use DateTimeImmutable;

interface Clock
{
    /** Return the current moment. */
    public function now(): DateTimeImmutable;
}
