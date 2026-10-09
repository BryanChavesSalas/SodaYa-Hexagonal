<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Tenancy;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\ConnectionInterface;
use Src\Shared\Domain\Contracts\SodaContext;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class AuthenticatedSodaContext implements SodaContext
{
    /** Receive the authentication manager and the connection that enforces the row policies. */
    public function __construct(
        private AuthFactory $auth,
        private ConnectionInterface $connection,
    ) {}

    /** Resolve the soda of the authenticated user and share it with the row policies. */
    public function current(): SodaId
    {
        $sodaId = data_get($this->auth->guard()->user(), 'soda_id');

        if (! is_string($sodaId)) {
            throw new AuthenticationException;
        }

        $this->connection->statement(
            "select set_config('app.soda_id', ?, ?)",
            [$sodaId, $this->connection->transactionLevel() > 0],
        );

        return new SodaId($sodaId);
    }
}
