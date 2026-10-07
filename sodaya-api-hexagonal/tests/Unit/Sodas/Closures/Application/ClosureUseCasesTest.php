<?php

declare(strict_types=1);

namespace Tests\Unit\Sodas\Closures\Application;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Closures\Application\DTOs\RegisterClosureCommand;
use Src\Sodas\Closures\Application\UseCases\CloseForToday;
use Src\Sodas\Closures\Application\UseCases\DeleteClosure;
use Src\Sodas\Closures\Application\UseCases\ListClosures;
use Src\Sodas\Closures\Application\UseCases\RegisterClosure;
use Src\Sodas\Closures\Domain\Entities\Closure;
use Src\Sodas\Closures\Domain\Exceptions\ClosureAlreadyExistsException;
use Src\Sodas\Closures\Domain\Exceptions\ClosureNotFoundException;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;
use Tests\Support\Sodas\InMemoryClosureRepository;

final class ClosureUseCasesTest extends TestCase
{
    private const string SODA_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22';

    private const string OTHER_SODA_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d33';

    private InMemoryClosureRepository $closures;

    private RegisterClosure $registerClosure;

    private DateTimeImmutable $now;

    /** Wire the use cases to an in-memory repository. */
    protected function setUp(): void
    {
        $this->closures = new InMemoryClosureRepository;
        $this->registerClosure = new RegisterClosure($this->closures);
        $this->now = new DateTimeImmutable('2026-10-07 13:45', new DateTimeZone('America/Costa_Rica'));
    }

    /** A closure is registered for a date with an optional reason. */
    public function test_registers_a_closure_for_a_date(): void
    {
        $closure = $this->registerClosure->execute($this->command('2026-12-25', 'Navidad'), $this->now);

        $this->assertSame($closure, $this->closures->find($closure->id, new SodaId(self::SODA_ID)));
        $this->assertSame('2026-12-25', $closure->date->value);
        $this->assertSame('Navidad', $closure->reason?->value);
    }

    /** The reason can be left out. */
    public function test_registers_a_closure_without_reason(): void
    {
        $closure = $this->registerClosure->execute($this->command('2026-12-25'), $this->now);

        $this->assertNull($closure->reason);
    }

    /** A date before today is rejected and nothing is stored. */
    public function test_rejects_a_past_date(): void
    {
        try {
            $this->registerClosure->execute($this->command('2026-10-06'), $this->now);
            $this->fail('A past date was accepted.');
        } catch (InvalidValueException) {
            $this->assertSame([], $this->closures->from(new SodaId(self::SODA_ID), new ClosureDate('2000-01-01')));
        }
    }

    /** A soda cannot close twice on the same date. */
    public function test_rejects_a_second_closure_on_the_same_date(): void
    {
        $this->registerClosure->execute($this->command('2026-12-25'), $this->now);

        $this->expectException(ClosureAlreadyExistsException::class);

        $this->registerClosure->execute($this->command('2026-12-25'), $this->now);
    }

    /** Another soda may close on the same date. */
    public function test_another_soda_may_close_on_the_same_date(): void
    {
        $this->registerClosure->execute($this->command('2026-12-25'), $this->now);

        $closure = $this->registerClosure->execute(
            new RegisterClosureCommand(self::OTHER_SODA_ID, '2026-12-25', null),
            $this->now,
        );

        $this->assertSame(self::OTHER_SODA_ID, $closure->sodaId->value);
    }

    /** Closing for today uses the current date. */
    public function test_closes_for_the_rest_of_today(): void
    {
        $closure = new CloseForToday($this->registerClosure)->execute(self::SODA_ID, 'Falta de gas', $this->now);

        $this->assertSame('2026-10-07', $closure->date->value);
        $this->assertSame('Falta de gas', $closure->reason?->value);
    }

    /** Closing for today twice is rejected. */
    public function test_cannot_close_for_today_twice(): void
    {
        $closeForToday = new CloseForToday($this->registerClosure);
        $closeForToday->execute(self::SODA_ID, null, $this->now);

        $this->expectException(ClosureAlreadyExistsException::class);

        $closeForToday->execute(self::SODA_ID, null, $this->now);
    }

    /** The listing shows today and later dates of the soda in order. */
    public function test_lists_closures_from_today_in_order(): void
    {
        $this->registerClosure->execute($this->command('2026-12-25'), $this->now);
        $this->registerClosure->execute($this->command('2026-10-07'), $this->now);
        $this->registerClosure->execute($this->command('2026-11-02'), $this->now);
        $this->registerClosure->execute(new RegisterClosureCommand(self::OTHER_SODA_ID, '2026-10-20', null), $this->now);

        $tomorrow = $this->now->modify('+1 day');
        $listClosures = new ListClosures($this->closures);

        $this->assertSame(['2026-10-07', '2026-11-02', '2026-12-25'], $this->dates($listClosures->execute(self::SODA_ID, $this->now)));
        $this->assertSame(['2026-11-02', '2026-12-25'], $this->dates($listClosures->execute(self::SODA_ID, $tomorrow)));
    }

    /** A closure of the soda can be deleted. */
    public function test_deletes_a_closure(): void
    {
        $closure = $this->registerClosure->execute($this->command('2026-12-25'), $this->now);

        new DeleteClosure($this->closures)->execute($closure->id->value, self::SODA_ID);

        $this->assertNull($this->closures->find($closure->id, new SodaId(self::SODA_ID)));
    }

    /** A closure of another soda cannot be deleted. */
    public function test_cannot_delete_a_closure_of_another_soda(): void
    {
        $closure = $this->registerClosure->execute($this->command('2026-12-25'), $this->now);

        $this->expectException(ClosureNotFoundException::class);

        new DeleteClosure($this->closures)->execute($closure->id->value, self::OTHER_SODA_ID);
    }

    /** Build a registration command for the scenario soda. */
    private function command(string $date, ?string $reason = null): RegisterClosureCommand
    {
        return new RegisterClosureCommand(self::SODA_ID, $date, $reason);
    }

    /**
     * Extract the dates of the closures.
     *
     * @param  list<Closure>  $closures
     * @return list<string>
     */
    private function dates(array $closures): array
    {
        return array_map(fn (Closure $closure): string => $closure->date->value, $closures);
    }
}
