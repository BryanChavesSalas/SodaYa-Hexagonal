<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Queue;

use DateTimeImmutable;
use Src\Ordering\Orders\Application\Contracts\OrderDeadlines;
use Src\Ordering\Orders\Domain\ValueObjects\OrderId;
use Src\Ordering\Orders\Infrastructure\Jobs\ExpireOrderJob;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class QueuedOrderDeadlines implements OrderDeadlines
{
    /** Leave in the queue a job that only becomes available at the deadline. */
    public function checkAt(OrderId $orderId, SodaId $sodaId, DateTimeImmutable $moment): void
    {
        ExpireOrderJob::dispatch($orderId->value, $sodaId->value)->delay($moment);
    }
}
