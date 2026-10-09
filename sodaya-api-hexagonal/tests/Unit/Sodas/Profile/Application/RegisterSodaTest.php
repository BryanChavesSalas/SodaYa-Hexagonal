<?php

declare(strict_types=1);

namespace Tests\Unit\Sodas\Profile\Application;

use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Sodas\Profile\Application\DTOs\RegisterSodaCommand;
use Src\Sodas\Profile\Application\UseCases\RegisterSoda;
use Tests\Support\Shared\RecordingTransactionRunner;
use Tests\Support\Sodas\InMemoryOwnerAccounts;
use Tests\Support\Sodas\InMemorySodaRepository;

final class RegisterSodaTest extends TestCase
{
    private InMemorySodaRepository $sodas;

    private InMemoryOwnerAccounts $owners;

    private RecordingTransactionRunner $transaction;

    private RegisterSoda $registerSoda;

    /** Wire the use case to in-memory ports. */
    protected function setUp(): void
    {
        $this->sodas = new InMemorySodaRepository;
        $this->owners = new InMemoryOwnerAccounts;
        $this->transaction = new RecordingTransactionRunner;
        $this->registerSoda = new RegisterSoda($this->sodas, $this->owners, $this->transaction);
    }

    /** The soda and its owner are registered together in a single transaction. */
    public function test_soda_and_owner_are_registered_in_one_transaction(): void
    {
        $soda = $this->registerSoda->execute($this->command(paymentAccountId: ' acct_123 '));

        $this->assertSame('Soda La Esquina', $soda->name->value);
        $this->assertSame('acct_123', $soda->paymentAccountId?->value);
        $this->assertEquals([$soda], $this->sodas->all());
        $this->assertSame(
            [['sodaId' => $soda->id->value, 'name' => 'Ana Mora', 'email' => 'ana@sodaya.test', 'password' => 'clave-segura']],
            $this->owners->registered(),
        );
        $this->assertSame(1, $this->transaction->runs());
        $this->assertSame(1, $this->transaction->committed());
        $this->assertSame(0, $this->transaction->rolledBack());
    }

    /** The payment account is optional. */
    public function test_soda_can_be_registered_without_a_payment_account(): void
    {
        $soda = $this->registerSoda->execute($this->command());

        $this->assertNull($soda->paymentAccountId);
    }

    /** A refusal of the owner account rolls the transaction back and reaches the caller. */
    public function test_owner_refusal_rolls_the_transaction_back(): void
    {
        $failure = new InvalidValueException('identity.email_already_registered');
        $this->owners->failWith($failure);

        try {
            $this->registerSoda->execute($this->command());
            $this->fail('The refusal of the owner account was swallowed.');
        } catch (InvalidValueException $exception) {
            $this->assertSame($failure, $exception);
            $this->assertSame(1, $this->transaction->runs());
            $this->assertSame(0, $this->transaction->committed());
            $this->assertSame(1, $this->transaction->rolledBack());
        }
    }

    /** An invalid soda name is refused before a transaction is opened. */
    public function test_invalid_soda_name_is_refused_before_the_transaction(): void
    {
        try {
            $this->registerSoda->execute($this->command(name: '   '));
            $this->fail('An invalid soda name was accepted.');
        } catch (InvalidValueException $exception) {
            $this->assertSame('sodas.name_invalid', $exception->translationKey());
            $this->assertSame(0, $this->transaction->runs());
            $this->assertSame([], $this->owners->registered());
        }
    }

    /** An invalid payment account is refused before a transaction is opened. */
    public function test_invalid_payment_account_is_refused_before_the_transaction(): void
    {
        try {
            $this->registerSoda->execute($this->command(paymentAccountId: '   '));
            $this->fail('An invalid payment account was accepted.');
        } catch (InvalidValueException $exception) {
            $this->assertSame('sodas.payment_account_id_invalid', $exception->translationKey());
            $this->assertSame(0, $this->transaction->runs());
            $this->assertSame([], $this->owners->registered());
        }
    }

    /** Build the command of a soda, overriding only what a scenario changes. */
    private function command(string $name = 'Soda La Esquina', ?string $paymentAccountId = null): RegisterSodaCommand
    {
        return new RegisterSodaCommand($name, 'Ana Mora', 'ana@sodaya.test', 'clave-segura', $paymentAccountId);
    }
}
