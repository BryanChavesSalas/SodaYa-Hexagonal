<?php

declare(strict_types=1);

namespace Src\Sodas\Profile\Domain\Contracts;

use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Domain\Entities\Soda;

interface SodaRepository
{
    /** Generate the identity for a new soda. */
    public function nextId(): SodaId;

    /** Persist a new or modified soda. */
    public function save(Soda $soda): void;
}
