<?php

declare(strict_types=1);

namespace Src\Loyalty\Points\Infrastructure\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Src\Loyalty\Points\Application\DTOs\AwardPurchasePointsCommand;
use Src\Loyalty\Points\Application\UseCases\AwardPurchasePoints;

/** Credits the points of a delivered order; running it again credits nothing. */
final class AwardPurchasePointsJob implements ShouldQueue
{
    use Queueable;

    /** Attempts before the job is recorded as failed. */
    public int $tries = 5;

    /** @var list<int> seconds to wait before the second, third and later attempts */
    public array $backoff = [10, 60, 300];

    /** Carry the customer, the order and its amount in colones. */
    public function __construct(
        public readonly string $customerId,
        public readonly string $orderId,
        public readonly int $amount,
    ) {}

    /** Credit the points. */
    public function handle(AwardPurchasePoints $awardPoints): void
    {
        $awardPoints->execute(new AwardPurchasePointsCommand($this->customerId, $this->orderId, $this->amount), now()->toDateTimeImmutable());
    }
}
