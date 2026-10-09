<?php

declare(strict_types=1);

namespace Src\Sodas\Profile\Domain\Entities;

use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Domain\ValueObjects\PaymentAccountId;
use Src\Sodas\Profile\Domain\ValueObjects\SodaName;

final readonly class Soda
{
    /** Hold the full state of a soda. */
    private function __construct(
        public SodaId $id,
        public SodaName $name,
        public ?PaymentAccountId $paymentAccountId,
    ) {}

    /** Register a soda, with or without a payment account. */
    public static function create(SodaId $id, SodaName $name, ?PaymentAccountId $paymentAccountId): self
    {
        return new self($id, $name, $paymentAccountId);
    }

    /** Rebuild a soda from its stored state. */
    public static function reconstitute(SodaId $id, SodaName $name, ?PaymentAccountId $paymentAccountId): self
    {
        return new self($id, $name, $paymentAccountId);
    }
}
