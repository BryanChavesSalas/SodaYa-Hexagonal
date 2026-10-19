<?php

declare(strict_types=1);

namespace Src\Loyalty\Points\Domain\ValueObjects;

enum MovementReason: string
{
    case Purchase = 'purchase';
    case Redemption = 'redemption';
}
