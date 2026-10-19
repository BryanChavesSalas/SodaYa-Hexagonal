<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Console;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Src\Ordering\Orders\Application\Contracts\ExpiredOrders;
use Src\Ordering\Orders\Application\DTOs\ExpireOrderCommand;
use Src\Ordering\Orders\Application\UseCases\ExpireOrder;
use Src\Shared\Infrastructure\Tenancy\SodaTransaction;

#[Signature('sodaya:expire-orders {--limit=500}')]
final class ExpireOrdersConsoleCommand extends Command
{
    /** Describe the command in Spanish for the command list. */
    public function __construct()
    {
        parent::__construct();

        $this->setDescription(__('ordering.expire_orders_description'));
    }

    /** Cancel every order whose deadline passed, each one in a transaction of its own soda. */
    public function handle(ExpiredOrders $expiredOrders, SodaTransaction $transaction, ExpireOrder $expireOrder): int
    {
        $now = now()->toDateTimeImmutable();
        $cancelled = 0;

        foreach ($expiredOrders->dueAt($now, (int) $this->option('limit')) as $order) {
            $expired = $transaction->run($order->sodaId, fn (): bool => $expireOrder->execute(
                new ExpireOrderCommand($order->orderId, $order->sodaId),
                $now,
            ));
            $cancelled += (int) $expired;
        }

        $this->components->info(trans_choice('ordering.orders_expired', $cancelled, ['count' => $cancelled]));

        return self::SUCCESS;
    }
}
