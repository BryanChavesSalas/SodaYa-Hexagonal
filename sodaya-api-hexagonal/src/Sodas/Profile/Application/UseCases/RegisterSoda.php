<?php

declare(strict_types=1);

namespace Src\Sodas\Profile\Application\UseCases;

use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Sodas\Profile\Application\Contracts\OwnerAccounts;
use Src\Sodas\Profile\Application\DTOs\RegisterSodaCommand;
use Src\Sodas\Profile\Domain\Contracts\SodaRepository;
use Src\Sodas\Profile\Domain\Entities\Soda;
use Src\Sodas\Profile\Domain\ValueObjects\PaymentAccountId;
use Src\Sodas\Profile\Domain\ValueObjects\SodaName;

final readonly class RegisterSoda
{
    /** Receive the soda repository, owner accounts and transaction ports. */
    public function __construct(
        private SodaRepository $sodas,
        private OwnerAccounts $owners,
        private TransactionRunner $transaction,
    ) {}

    /** Register a soda and its owner together, so neither exists without the other. */
    public function execute(RegisterSodaCommand $command): Soda
    {
        $name = new SodaName($command->name);
        $paymentAccountId = $command->paymentAccountId === null ? null : new PaymentAccountId($command->paymentAccountId);

        return $this->transaction->run(function () use ($command, $name, $paymentAccountId): Soda {
            $soda = Soda::create($this->sodas->nextId(), $name, $paymentAccountId);

            $this->sodas->save($soda);
            $this->owners->registerOwner($soda->id, $command->ownerName, $command->ownerEmail, $command->ownerPassword);

            return $soda;
        });
    }
}
