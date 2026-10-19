<?php

declare(strict_types=1);

namespace Src\Catalog\Promotions\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class PromotionNotFoundException extends NotFoundException
{
    /** Build the exception for a happy hour missing from the soda. */
    public static function create(): self
    {
        return new self('catalog.promotion_not_found');
    }
}
