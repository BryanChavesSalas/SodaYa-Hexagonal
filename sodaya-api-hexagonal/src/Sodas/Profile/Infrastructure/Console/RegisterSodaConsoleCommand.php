<?php

declare(strict_types=1);

namespace Src\Sodas\Profile\Infrastructure\Console;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\Sodas\Profile\Application\DTOs\RegisterSodaCommand;
use Src\Sodas\Profile\Application\UseCases\RegisterSoda;

#[Signature('sodaya:register-soda {name} {owner-name} {owner-email} {--payment-account=} {--password=}')]
final class RegisterSodaConsoleCommand extends Command
{
    /** Describe the command in Spanish for the command list. */
    public function __construct()
    {
        parent::__construct();

        $this->setDescription(__('sodas.register_soda_description'));
    }

    /** Register the soda and its owner, answering 0 on success and 1 when a datum is refused. */
    public function handle(RegisterSoda $registerSoda): int
    {
        $ownerEmail = (string) $this->argument('owner-email');
        $paymentAccount = $this->option('payment-account');
        $password = $this->option('password');

        if ($password === null) {
            $password = $this->secret(__('sodas.owner_password_prompt'), false);
        }

        try {
            $soda = $registerSoda->execute(new RegisterSodaCommand(
                (string) $this->argument('name'),
                (string) $this->argument('owner-name'),
                $ownerEmail,
                is_string($password) ? $password : '',
                is_string($paymentAccount) ? $paymentAccount : null,
            ));
        } catch (DomainException $exception) {
            $this->components->error(__($exception->translationKey(), $exception->parameters()));

            return self::FAILURE;
        }

        $this->components->info(__('sodas.soda_registered', [
            'name' => $soda->name->value,
            'id' => $soda->id->value,
            'email' => $ownerEmail,
        ]));

        return self::SUCCESS;
    }
}
