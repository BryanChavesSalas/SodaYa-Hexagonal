<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Src\Ordering\Orders\Application\Contracts\NoticeChannel;
use Src\Ordering\Orders\Application\Contracts\Recipients;
use Src\Ordering\Orders\Application\DTOs\Recipient;
use Src\Ordering\Orders\Domain\Contracts\OrderRepository;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\Exceptions\OrderNotFoundException;
use Src\Ordering\Orders\Domain\ValueObjects\OrderId;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Shared\Infrastructure\Tenancy\SodaTransaction;

/** Tells the customer what happened to an order through the channel the configuration chooses. */
final class NotifyCustomerJob implements ShouldQueue
{
    use Queueable;

    public const string ORDER_READY = 'order_ready';

    public const string ORDER_CANCELLED = 'order_cancelled';

    /** Attempts before the job is recorded as failed. */
    public int $tries = 5;

    /** @var list<int> seconds to wait before the second, third and later attempts */
    public array $backoff = [10, 60, 300];

    /** Carry the order, its soda and what happened to it; everything else is read when the job runs. */
    public function __construct(
        public readonly string $orderId,
        public readonly string $sodaId,
        public readonly string $event,
    ) {}

    /**
     * Read the order inside a transaction of its soda, find out where its customer is reached and send the notice
     * through the channel the worker is configured with: the job asks for the port and never picks a channel.
     */
    public function handle(SodaTransaction $transaction, OrderRepository $orders, Recipients $recipients, NoticeChannel $channel): void
    {
        $order = $transaction->run($this->sodaId, fn (): Order => $orders->find(new OrderId($this->orderId), new SodaId($this->sodaId))
            ?? throw OrderNotFoundException::create());
        $to = $recipients->of($order->customerId);
        $parameters = $this->parameters($order, $to);

        $channel->send($to, __("notices.{$this->event}.subject", $parameters), __("notices.{$this->event}.message", $parameters));
    }

    /**
     * Placeholders of the texts: who, which number and, for a cancellation, who cancelled and whether there is a refund.
     *
     * @return array<string, int|string>
     */
    private function parameters(Order $order, Recipient $to): array
    {
        $parameters = ['name' => $to->name, 'number' => $order->pickupNumber->value];

        if ($this->event === self::ORDER_CANCELLED) {
            $parameters['origin'] = __('notices.origins.'.$order->cancellationOrigin?->value);
            $parameters['refund'] = $order->isPaid()
                ? __('notices.refund', ['amount' => number_format($order->total(), 0, ',', '.')])
                : __('notices.no_refund');
        }

        return $parameters;
    }
}
