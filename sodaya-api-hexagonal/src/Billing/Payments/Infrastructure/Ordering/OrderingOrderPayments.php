<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Ordering;

use DateTimeImmutable;
use Src\Billing\Payments\Application\Contracts\OrderPayments;
use Src\Billing\Payments\Domain\ValueObjects\OrderId;
use Src\Ordering\Orders\Application\DTOs\ApproveOrderPaymentCommand;
use Src\Ordering\Orders\Application\UseCases\ApproveOrderPayment;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class OrderingOrderPayments implements OrderPayments
{
    /** Receive the use case of the orders context that records an approved payment. */
    public function __construct(private ApproveOrderPayment $approveOrderPayment) {}

    /** Hand the approval to the orders context, which marks the order paid and starts the time the kitchen has to confirm it. */
    public function markPaid(OrderId $orderId, SodaId $sodaId, DateTimeImmutable $at): void
    {
        $this->approveOrderPayment->execute(new ApproveOrderPaymentCommand($orderId->value, $sodaId->value), $at);
    }
}
