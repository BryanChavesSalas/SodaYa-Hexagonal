<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** Provisional: it leaves the refund in the log until the payment module refunds for real. */
final class RequestRefundJob implements ShouldQueue
{
    use Queueable;

    /** Attempts before the job is recorded as failed. */
    public int $tries = 5;

    /** @var list<int> seconds to wait before the second, third and later attempts */
    public array $backoff = [10, 60, 300];

    /** Carry the order to refund and the amount in colones. */
    public function __construct(
        public readonly string $orderId,
        public readonly string $sodaId,
        public readonly int $amount,
    ) {}

    /** Ask for the refund. */
    public function handle(): void
    {
        Log::warning('Refund pending for a cancelled order.', [
            'order_id' => $this->orderId,
            'soda_id' => $this->sodaId,
            'amount' => $this->amount,
        ]);
    }
}
