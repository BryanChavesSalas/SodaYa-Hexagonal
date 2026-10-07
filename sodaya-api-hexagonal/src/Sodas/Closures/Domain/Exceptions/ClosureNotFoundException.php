<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class ClosureNotFoundException extends NotFoundException
{
    public static function create(): self
    {
        return new self('closure_not_found');
    }
}
