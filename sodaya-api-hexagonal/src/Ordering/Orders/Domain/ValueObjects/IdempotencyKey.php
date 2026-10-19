<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class IdempotencyKey
{
    public const int MAX_LENGTH = 100;

    private const string FINGERPRINT_PATTERN = '/^[0-9a-f]{64}$/';

    /** Keep the key the customer chose for a request and the fingerprint of what the request asked for. */
    public function __construct(
        public string $value,
        public string $fingerprint,
    ) {
        if (trim($value) === '' || mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidValueException('ordering.idempotency_key_invalid', ['max' => self::MAX_LENGTH]);
        }

        if (preg_match(self::FINGERPRINT_PATTERN, $fingerprint) !== 1) {
            throw new InvalidValueException('ordering.idempotency_key_invalid', ['max' => self::MAX_LENGTH]);
        }
    }

    /**
     * Summarize the lines of a request so two requests with the same dishes and quantities, in any order, match.
     *
     * @param  list<array{dish_id: string, quantity: int}>  $lines
     */
    public static function fingerprintOf(array $lines): string
    {
        $lines = array_map(fn (array $line): array => ['dish_id' => $line['dish_id'], 'quantity' => $line['quantity']], $lines);
        usort($lines, fn (array $a, array $b): int => strcmp($a['dish_id'], $b['dish_id']));

        return hash('sha256', (string) json_encode($lines));
    }

    /** Tell whether another key is the same request sent again: same key and same content. */
    public function repeats(self $other): bool
    {
        return $this->value === $other->value && $this->fingerprint === $other->fingerprint;
    }
}
