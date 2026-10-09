<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Application\UseCases;

use DateTimeImmutable;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Closures\Application\DTOs\RegisterClosureCommand;
use Src\Sodas\Closures\Domain\Contracts\ClosureRepository;
use Src\Sodas\Closures\Domain\Entities\Closure;
use Src\Sodas\Closures\Domain\Exceptions\ClosureAlreadyExistsException;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureReason;

final readonly class RegisterClosure
{
    /** Receive the closure repository port. */
    public function __construct(private ClosureRepository $closures) {}

    /** Register a closure of the soda for today or a later date. */
    public function execute(RegisterClosureCommand $command, DateTimeImmutable $now): Closure
    {
        $sodaId = new SodaId($command->sodaId);
        $date = new ClosureDate($command->date);

        if ($this->closures->onDate($sodaId, $date) !== null) {
            throw ClosureAlreadyExistsException::create();
        }

        $closure = Closure::create(
            $this->closures->nextId(),
            $sodaId,
            $date,
            $command->reason === null ? null : new ClosureReason($command->reason),
            ClosureDate::fromMoment($now),
        );

        $this->closures->save($closure);

        return $closure;
    }
}
