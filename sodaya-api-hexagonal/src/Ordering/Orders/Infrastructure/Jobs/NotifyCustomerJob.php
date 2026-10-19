<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** Provisional: it leaves the notice in the log until the notifications module sends it by mail or push. */
final class NotifyCustomerJob implements ShouldQueue
{
    use Queueable;

    /** Attempts before the job is recorded as failed. */
    public int $tries = 5;

    /** @var list<int> seconds to wait before the second, third and later attempts */
    public array $backoff = [10, 60, 300];

    /** Carry who is notified, about which order and what happened. */
    public function __construct(
        public readonly string $customerId,
        public readonly string $orderId,
        public readonly string $event,
        public readonly ?string $reason = null,
    ) {}

    /** Send the notice. */
    public function handle(): void
    {
        Log::info('Customer notice.', [
            'customer_id' => $this->customerId,
            'order_id' => $this->orderId,
            'event' => $this->event,
            'reason' => $this->reason,
        ]);
    }
}
