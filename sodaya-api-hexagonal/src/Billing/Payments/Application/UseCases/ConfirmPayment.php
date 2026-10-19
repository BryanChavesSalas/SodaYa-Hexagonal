<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Application\UseCases;

use DateTimeImmutable;
use Src\Billing\Payments\Application\Contracts\OrderPayments;
use Src\Billing\Payments\Application\Contracts\PayableOrders;
use Src\Billing\Payments\Application\Contracts\PaymentGateway;
use Src\Billing\Payments\Application\DTOs\ConfirmPaymentCommand;
use Src\Billing\Payments\Domain\Contracts\PaymentRepository;
use Src\Billing\Payments\Domain\Entities\Payment;
use Src\Billing\Payments\Domain\Exceptions\OrderNotFoundException;
use Src\Billing\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Billing\Payments\Domain\ValueObjects\CustomerId;
use Src\Billing\Payments\Domain\ValueObjects\OrderId;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class ConfirmPayment
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
     * Tell the real state of the latest payment of an order of the customer. A payment the provider already decided
     * is answered as stored. A pending one with a reference is asked about to the provider, outside of any transaction,
     * and the answer is recorded in the payment and, when approved, in the order, in one transaction. A pending one
     * without a reference never reached the provider, so there is nothing to ask. Nothing the client says about the
     * state is ever used, and a second run finds the payment closed and changes nothing.
     */
    public function execute(ConfirmPaymentCommand $command, DateTimeImmutable $now): Payment
    {
        $order = $this->orders->find(new OrderId($command->orderId), new CustomerId($command->customerId))
            ?? throw OrderNotFoundException::create();
        $sodaId = new SodaId($order->sodaId);

        $payment = $this->payments->latestOf(new OrderId($order->id), $sodaId)
            ?? throw PaymentNotFoundException::create();

        if (! $payment->isPending() || $payment->reference === null) {
            return $payment;
        }

        $result = $this->gateway->status($payment->reference);

        return $this->transaction->run(function () use ($payment, $result, $now, $sodaId): Payment {
            $payment = $this->payments->findForUpdate($payment->id, $sodaId) ?? throw PaymentNotFoundException::create();

            if (! $payment->settle($result, $now)) {
                return $payment;
            }

            $this->payments->save($payment);

            if ($payment->isApproved()) {
                $this->orderPayments->markPaid($payment->orderId, $payment->sodaId, $now);
            }

            return $payment;
        });
    }
}
