<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\BusinessRuleException;

final class PaymentRejectedException extends BusinessRuleException
{
    /** Build the exception for a charge the bank declined, with the code it gave. */
    public static function create(string $declineCode): self
    {
        return new self('payments.rejected', ['code' => $declineCode]);
    }

    /** The bank declined the charge; the customer may try again while the order stays pending. */
    public function rule(): string
    {
        return 'pago-rechazado';
    }
}
