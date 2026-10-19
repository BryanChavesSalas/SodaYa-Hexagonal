<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Application\Contracts;

use Src\Billing\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Billing\Payments\Domain\ValueObjects\ChargeResult;

interface PaymentGateway
{
    /** Name of the provider, as it is kept with every payment it handles. */
    public function provider(): string;

    /**
     * Charge the amount once: a retry with the same idempotency key answers the same charge instead of making another.
     *
     * @throws GatewayUnavailableException when the provider does not answer or answers with an error
     */
    public function charge(ChargeRequest $request): ChargeResult;

    /**
     * Ask the provider what happened with a charge it already knows.
     *
     * @throws GatewayUnavailableException when the provider does not answer or answers with an error
     */
    public function status(ChargeReference $reference): ChargeResult;
}
