<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\UseCases;

use DateTimeImmutable;
use Src\Ordering\Orders\Application\DTOs\CancelOrderCommand;
use Src\Ordering\Orders\Application\Services\CancellationEffects;
use Src\Ordering\Orders\Domain\Contracts\OrderRepository;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\Exceptions\OrderNotFoundException;
use Src\Ordering\Orders\Domain\ValueObjects\CancellationOrigin;
use Src\Ordering\Orders\Domain\ValueObjects\OrderId;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class CancelOrder
{
    /** Receive the order repository, the effects of a cancellation and the transaction port. */
    public function __construct(
        private OrderRepository $orders,
        private CancellationEffects $effects,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Cancel an order of the soda and give its portions back in the same transaction; a cancelled order stays as it is.
     * Once the cancellation is committed, a paid order asks for its refund, and the customer hears about it
     * unless the customer cancelled.
     */
    public function execute(CancelOrderCommand $command, DateTimeImmutable $now): Order
    {
        [$order, $cancelled] = $this->transaction->run(function () use ($command, $now): array {
            $order = $this->orders->findForUpdate(new OrderId($command->orderId), new SodaId($command->sodaId))
                ?? throw OrderNotFoundException::create();

            $cancelled = $order->cancel(CancellationOrigin::from($command->origin), $now);

            if ($cancelled) {
                $this->effects->persist($order);
            }

            return [$order, $cancelled];
        });

        if ($cancelled) {
            $this->effects->announce($order);
        }

        return $order;
    }
}
