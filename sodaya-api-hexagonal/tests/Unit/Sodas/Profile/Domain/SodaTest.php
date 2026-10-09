<?php

declare(strict_types=1);

namespace Tests\Unit\Sodas\Profile\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Domain\Entities\Soda;
use Src\Sodas\Profile\Domain\ValueObjects\PaymentAccountId;
use Src\Sodas\Profile\Domain\ValueObjects\SodaName;

final class SodaTest extends TestCase
{
    private const string SODA_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22';

    /** A soda can be registered with a payment account. */
    public function test_soda_can_be_created_with_a_payment_account(): void
    {
        $soda = Soda::create(
            new SodaId(self::SODA_ID),
            new SodaName('Soda La Esquina'),
            new PaymentAccountId('acct_123'),
        );

        $this->assertSame(self::SODA_ID, $soda->id->value);
        $this->assertSame('Soda La Esquina', $soda->name->value);
        $this->assertSame('acct_123', $soda->paymentAccountId?->value);
    }

    /** A soda can be registered before it has a payment account. */
    public function test_soda_can_be_created_without_a_payment_account(): void
    {
        $soda = Soda::create(new SodaId(self::SODA_ID), new SodaName('Soda La Esquina'), null);

        $this->assertNull($soda->paymentAccountId);
    }

    /** A stored soda is rebuilt with the state it was saved with. */
    public function test_soda_can_be_reconstituted(): void
    {
        $soda = Soda::reconstitute(
            new SodaId(self::SODA_ID),
            new SodaName('Soda La Esquina'),
            new PaymentAccountId('acct_123'),
        );

        $this->assertSame(self::SODA_ID, $soda->id->value);
        $this->assertSame('Soda La Esquina', $soda->name->value);
        $this->assertSame('acct_123', $soda->paymentAccountId?->value);
    }

    /** The name is trimmed and limited in length. */
    public function test_soda_name_is_trimmed_and_limited(): void
    {
        $this->assertSame('Soda La Esquina', new SodaName('  Soda La Esquina  ')->value);
        $this->assertSame(120, mb_strlen(new SodaName(str_repeat('ñ', 120))->value));
    }

    /** A blank or oversized name is rejected with a translatable error. */
    #[DataProvider('invalidNames')]
    public function test_soda_name_rejects_invalid_values(string $name): void
    {
        try {
            new SodaName($name);
            $this->fail('An invalid soda name was accepted.');
        } catch (InvalidValueException $exception) {
            $this->assertSame('sodas.name_invalid', $exception->translationKey());
        }
    }

    /**
     * Names that break the invariant of the value object.
     *
     * @return array<string, array{string}>
     */
    public static function invalidNames(): array
    {
        return [
            'empty' => [''],
            'blank' => ['   '],
            'too long' => [str_repeat('a', 121)],
        ];
    }

    /** The payment account identifier is trimmed and limited in length. */
    public function test_payment_account_id_is_trimmed_and_limited(): void
    {
        $this->assertSame('acct_123', new PaymentAccountId('  acct_123  ')->value);
        $this->assertSame(255, mb_strlen(new PaymentAccountId(str_repeat('a', 255))->value));
    }

    /** A blank or oversized payment account identifier is rejected with a translatable error. */
    #[DataProvider('invalidPaymentAccountIds')]
    public function test_payment_account_id_rejects_invalid_values(string $value): void
    {
        try {
            new PaymentAccountId($value);
            $this->fail('An invalid payment account identifier was accepted.');
        } catch (InvalidValueException $exception) {
            $this->assertSame('sodas.payment_account_id_invalid', $exception->translationKey());
        }
    }

    /**
     * Identifiers that break the invariant of the value object.
     *
     * @return array<string, array{string}>
     */
    public static function invalidPaymentAccountIds(): array
    {
        return [
            'empty' => [''],
            'blank' => ['   '],
            'too long' => [str_repeat('a', 256)],
        ];
    }
}
