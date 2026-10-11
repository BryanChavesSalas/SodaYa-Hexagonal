<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Tenancy;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ScopeRequestToSoda
{
    public const string FROM_USER = 'user';

    public const string FROM_ROUTE = 'route';

    /** Receive the transaction that reaches the rows of one soda. */
    public function __construct(private SodaTransaction $transaction) {}

    /** Run the request in one transaction scoped to the soda of the authenticated user or, with "route", of the URL. */
    public function handle(Request $request, Closure $next, string $source = self::FROM_USER): Response
    {
        $sodaId = $source === self::FROM_ROUTE ? $request->route('soda') : $request->user()?->soda_id;

        return $this->transaction->run(is_string($sodaId) ? $sodaId : null, fn (): Response => $next($request));
    }
}
