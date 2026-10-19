<?php

declare(strict_types=1);

namespace Src\Catalog\Promotions\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\BusinessRuleException;

final class PromotionOverlapsException extends BusinessRuleException
{
    public const string RULE = 'promocion-traslapada';

    /** Build the exception for a happy hour that shares a minute with another of the same soda and day. */
    public static function create(): self
    {
        return new self('catalog.promotion_overlaps');
    }

    /** Name of the rule, published as the type of the problem. */
    public function rule(): string
    {
        return self::RULE;
    }
}
