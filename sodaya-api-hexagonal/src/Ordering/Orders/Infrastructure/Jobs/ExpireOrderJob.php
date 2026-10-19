<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Src\Ordering\Orders\Application\DTOs\ExpireOrderCommand;
use Src\Ordering\Orders\Application\UseCases\ExpireOrder;
use Src\Shared\Infrastructure\Tenancy\SodaTransaction;

/** Runs when the deadline of an order arrives and cancels it if nothing happened in time. */
final class ExpireOrderJob implements ShouldQueue
{
    use Queueable;

    /** Attempts before the job is recorded as failed. */
    public int $tries = 5;

    /** @var list<int> seconds to wait before the second, third and later attempts */
    public array $backoff = [10, 60, 300];

    /** Carry the order and its soda. */
    public function __construct(
        public readonly string $orderId,
        public readonly string $sodaId,
    ) {}

    /** Expire the order inside a transaction of its soda: a worker has no request to take the soda from. */
    public function handle(SodaTransaction $transaction, ExpireOrder $expireOrder): void
    {
        $transaction->run($this->sodaId, fn (): bool => $expireOrder->execute(
            new ExpireOrderCommand($this->orderId, $this->sodaId),
            now()->toDateTimeImmutable(),
        ));
    }
}
