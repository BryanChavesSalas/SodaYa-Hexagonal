<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Loyalty;

use Src\Loyalty\Points\Infrastructure\Jobs\AwardPurchasePointsJob;
use Src\Ordering\Orders\Application\Contracts\LoyaltyRewards;
use Src\Ordering\Orders\Domain\Entities\Order;

final readonly class QueuedLoyaltyRewards implements LoyaltyRewards
{
    /** Leave in the queue the points of the order: the total of its lines, without the tip. */
    public function orderDelivered(Order $order): void
    {
        AwardPurchasePointsJob::dispatch($order->customerId->value, $order->id->value, $order->total());
    }
}
