<?php

declare(strict_types=1);

namespace Tests\Support\Sodas;

use SensitiveParameter;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Application\Contracts\OwnerAccounts;
use Throwable;

final class InMemoryOwnerAccounts implements OwnerAccounts
{
    /** @var list<array{sodaId: string, name: string, email: string, password: string}> */
    private array $registered = [];

    private ?Throwable $failure = null;

    /** Make every later registration fail with the given error. */
    public function failWith(Throwable $failure): void
    {
        $this->failure = $failure;
    }

    /** Record the owner unless a failure was set. */
    public function registerOwner(
        SodaId $sodaId,
        string $name,
        string $email,
        #[SensitiveParameter] string $password,
    ): void {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->registered[] = ['sodaId' => $sodaId->value, 'name' => $name, 'email' => $email, 'password' => $password];
    }

    /**
     * List the owners registered so far.
     *
     * @return list<array{sodaId: string, name: string, email: string, password: string}>
     */
    public function registered(): array
    {
        return $this->registered;
    }
}
