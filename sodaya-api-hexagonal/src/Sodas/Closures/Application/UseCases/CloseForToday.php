<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Application\UseCases;

use DateTimeImmutable;
use Src\Sodas\Closures\Application\DTOs\RegisterClosureCommand;
use Src\Sodas\Closures\Domain\Entities\Closure;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;

final readonly class CloseForToday
{
    /** Reuse the registration of a closure. */
    public function __construct(private RegisterClosure $registerClosure) {}

    /** Close the soda for the rest of the current day. */
    public function execute(string $sodaId, ?string $reason, DateTimeImmutable $now): Closure
    {
        return $this->registerClosure->execute(
            new RegisterClosureCommand($sodaId, ClosureDate::fromMoment($now)->value, $reason),
            $now,
        );
    }
}
