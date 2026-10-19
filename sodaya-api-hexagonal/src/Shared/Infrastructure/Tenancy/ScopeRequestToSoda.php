<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Tenancy;

use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final readonly class ScopeRequestToSoda
{
    public const string FROM_USER = 'user';

    public const string FROM_ROUTE = 'route';

    public const string FROM_CUSTOMER = 'customer';

    public const string FROM_ORDER = 'order';

    /** Attribute of the request that carries the soda of the order, which came neither with the person nor with the URL. */
    public const string SODA_ATTRIBUTE = 'soda_id';

    /** Receive the transaction that reaches the rows of one soda and the port that tells the soda of an order. */
    public function __construct(private SodaTransaction $transaction, private SodaOfOrder $sodaOfOrder) {}

    /**
     * Run the request in one transaction scoped to a soda: the one of the authenticated staff member ("user") or the one
     * of the URL ("route"). A customer has no soda: "customer" runs with no soda and the authenticated customer, who reads
     * their own orders; "order" also looks up, as that customer, the order of the URL and fixes its soda, so everything
     * that follows works on the soda of the order. An order the customer cannot see is not found.
     */
    public function handle(Request $request, Closure $next, string $source = self::FROM_USER): Response
    {
        return match ($source) {
            self::FROM_USER => $this->transaction->run(self::string($request->user()?->soda_id), fn (): Response => $next($request)),
            self::FROM_ROUTE => $this->transaction->run(self::string($request->route('soda')), fn (): Response => $next($request)),
            self::FROM_CUSTOMER => $this->transaction->run(null, fn (): Response => $next($request), self::customer($request)),
            self::FROM_ORDER => $this->transaction->run(null, function () use ($request, $next): Response {
                $sodaId = $this->sodaOfOrder->resolve((string) $request->route('pedido'));
                $this->transaction->scope($sodaId);
                $request->attributes->set(self::SODA_ATTRIBUTE, $sodaId);

                return $next($request);
            }, self::customer($request)),
            default => throw new InvalidArgumentException("Unknown source of the soda: {$source}."),
        };
    }

    /** Identifier of the authenticated customer. */
    private static function customer(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }

    /** The value when it is a string; otherwise nothing. */
    private static function string(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
