<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\UseCases;

use DateTimeImmutable;
use Src\Ordering\Orders\Application\DTOs\ExpireOrderCommand;
use Src\Ordering\Orders\Application\Services\CancellationEffects;
use Src\Ordering\Orders\Domain\Contracts\OrderRepository;
use Src\Ordering\Orders\Domain\ValueObjects\OrderId;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class ExpireOrder
{
    /** Receive the order repository, the effects of a cancellation and the transaction port. */
    public function __construct(
        private OrderRepository $orders,
        private CancellationEffects $effects,
        private TransactionRunner $transaction,
    ) {}

    /** Cancel the order if its time ran out, with the same effects as any cancellation; answer whether it did. */
    public function execute(ExpireOrderCommand $command, DateTimeImmutable $now): bool
    {
        $expired = $this->transaction->run(function () use ($command, $now) {
            $order = $this->orders->findForUpdate(new OrderId($command->orderId), new SodaId($command->sodaId));

            if ($order === null || ! $order->expire($now)) {
                return null;
            }

            $this->effects->persist($order);

            return $order;
        });

        if ($expired === null) {
            return false;
        }

        $this->effects->announce($expired);

        return true;
    }
}
