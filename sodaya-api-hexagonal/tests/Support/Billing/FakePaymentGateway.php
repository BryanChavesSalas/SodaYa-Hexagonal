<?php

declare(strict_types=1);

namespace Tests\Support\Billing;

use Src\Billing\Payments\Application\Contracts\PaymentGateway;
use Src\Billing\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Billing\Payments\Domain\ValueObjects\ChargeResult;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;

final class FakePaymentGateway implements PaymentGateway
{
    public const string PROVIDER = 'fake';

    public const string REFERENCE = 'pi_fake';

    /** @var list<ChargeRequest> charges asked for, in order */
    public private(set) array $charges = [];

    /** @var list<ChargeReference> charges asked about, in order */
    public private(set) array $statusChecks = [];

    private ChargeResult|GatewayUnavailableException $answer;

    /** Answer every charge and every status check the same way; approved unless told otherwise. */
    public function __construct(ChargeResult|GatewayUnavailableException|null $answer = null)
    {
        $this->answer = $answer ?? new ChargeResult(PaymentStatus::Approved, new ChargeReference(self::REFERENCE));
    }

    /** Change what the provider answers from now on. */
    public function answerWith(ChargeResult|GatewayUnavailableException $answer): void
    {
        $this->answer = $answer;
    }

    /** Name of the fake provider. */
    public function provider(): string
    {
        return self::PROVIDER;
    }

    /** Remember the charge and answer what was configured, or fail like a provider that is down. */
    public function charge(ChargeRequest $request): ChargeResult
    {
        $this->charges[] = $request;

        return $this->answer();
    }

    /** Remember the check and answer what was configured, or fail like a provider that is down. */
    public function status(ChargeReference $reference): ChargeResult
    {
        $this->statusChecks[] = $reference;

        return $this->answer();
    }

    /** The configured answer, thrown when it is an exception. */
    private function answer(): ChargeResult
    {
        if ($this->answer instanceof GatewayUnavailableException) {
            throw $this->answer;
        }

        return $this->answer;
    }
}
