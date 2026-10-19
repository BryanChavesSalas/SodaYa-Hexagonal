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
        public ?string $logoPath,
    ) {}

    /** Register a soda, with or without a payment account. */
    public static function create(SodaId $id, SodaName $name, ?PaymentAccountId $paymentAccountId): self
    {
        return new self($id, $name, $paymentAccountId, null);
    }

    /** Rebuild a soda from its stored state. */
    public static function reconstitute(
        SodaId $id,
        SodaName $name,
        ?PaymentAccountId $paymentAccountId,
        ?string $logoPath = null,
    ): self {
        return new self($id, $name, $paymentAccountId, $logoPath);
    }

    /** Return the same soda showing a new logo. */
    public function withLogo(string $path): self
    {
        return new self($this->id, $this->name, $this->paymentAccountId, $path);
    }
}
