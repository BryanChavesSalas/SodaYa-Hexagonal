<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Sodas;

use DateTimeImmutable;
use Src\Ordering\Orders\Application\Contracts\SodaSchedule;
use Src\Ordering\Orders\Domain\Exceptions\SodaNotFoundException;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\OpeningHours\Application\UseCases\CheckSodaIsOpen;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;

final readonly class OpeningHoursSodaSchedule implements SodaSchedule
{
    /** Receive the use case of the Sodas context that knows the schedule and the closures. */
    public function __construct(private CheckSodaIsOpen $checkSodaIsOpen) {}

    /** Make sure the soda exists and ask the Sodas context whether it is open at the moment. */
    public function isOpenAt(SodaId $sodaId, DateTimeImmutable $moment): bool
    {
        if (! SodaModel::query()->whereKey($sodaId->value)->exists()) {
            throw SodaNotFoundException::create();
        }

        return $this->checkSodaIsOpen->execute($sodaId->value, $moment);
    }
}
