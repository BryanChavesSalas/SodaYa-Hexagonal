<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Application\UseCases;

use DateTimeImmutable;
use Src\Billing\Payments\Application\Contracts\OrderPayments;
use Src\Billing\Payments\Application\Contracts\PayableOrders;
use Src\Billing\Payments\Application\Contracts\PaymentGateway;
use Src\Billing\Payments\Application\DTOs\PayOrderCommand;
use Src\Billing\Payments\Domain\Contracts\PaymentRepository;
use Src\Billing\Payments\Domain\Entities\Payment;
use Src\Billing\Payments\Domain\Exceptions\OrderAlreadyPaidException;
use Src\Billing\Payments\Domain\Exceptions\OrderNotFoundException;
use Src\Billing\Payments\Domain\Exceptions\OrderNotPayableException;
use Src\Billing\Payments\Domain\Exceptions\PaymentRejectedException;
use Src\Billing\Payments\Domain\ValueObjects\Amount;
use Src\Billing\Payments\Domain\ValueObjects\CustomerId;
use Src\Billing\Payments\Domain\ValueObjects\OrderId;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class PayOrder
{
    /** Receive the orders that can be paid, the payment repository, the gateway, the orders to mark paid and the transaction ports. */
    public function __construct(
        private PayableOrders $orders,
        private PaymentRepository $payments,
        private PaymentGateway $gateway,
        private OrderPayments $orderPayments,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Pay a pending order of the customer in three steps. A short transaction checks the order and opens the attempt,
     * or answers the live one the order already has. The provider is asked outside of any transaction, because it can
     * take ten seconds and a charge has no rollback. A second transaction records the outcome in the payment and, when
     * approved, in the order, together or not at all. The amount is the one of the stored order: the command carries none.
     * A rejected charge is saved and then reported, so the customer can try again while the order stays pending.
     */
    public function execute(PayOrderCommand $command, DateTimeImmutable $now): Payment
    {
        $payment = $this->transaction->run(fn (): Payment => $this->openAttempt($command, $now));

        $result = $this->gateway->charge($payment->asChargeRequest($command->paymentMethod));

        $this->transaction->run(function () use ($payment, $result, $now): void {
            if (! $payment->settle($result, $now)) {
                return;
            }

            $this->payments->save($payment);

            if ($payment->isApproved()) {
                $this->orderPayments->markPaid($payment->orderId, $payment->sodaId, $now);
            }
        });

        if ($payment->isRejected()) {
            throw PaymentRejectedException::create((string) $payment->declineCode);
        }

        return $payment;
    }

    /** Check that the order is the customer's, unpaid and pending, and open the attempt for the amount it is worth. */
    private function openAttempt(PayOrderCommand $command, DateTimeImmutable $now): Payment
    {
        $order = $this->orders->find(new OrderId($command->orderId), new CustomerId($command->customerId))
            ?? throw OrderNotFoundException::create();

        if ($order->paid) {
            throw OrderAlreadyPaidException::create();
        }

        if (! $order->pending) {
            throw OrderNotPayableException::create();
        }

        return $this->payments->open(Payment::open(
            $this->payments->nextId(),
            new OrderId($order->id),
            new SodaId($order->sodaId),
            new CustomerId($order->customerId),
            $this->gateway->provider(),
            new Amount($order->amount),
            $now,
        ));
    }
}
