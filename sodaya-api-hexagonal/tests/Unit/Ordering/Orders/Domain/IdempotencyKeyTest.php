<?php

declare(strict_types=1);

namespace Tests\Unit\Ordering\Orders\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Ordering\Orders\Domain\ValueObjects\IdempotencyKey;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Tests\Support\Ordering\Orders;

final class IdempotencyKeyTest extends TestCase
{
    /** The same dishes and quantities give the same fingerprint, whatever the order of the lines. */
    public function test_fingerprint_ignores_the_order_of_the_lines(): void
    {
        $one = IdempotencyKey::fingerprintOf([['dish_id' => Orders::CASADO, 'quantity' => 2], ['dish_id' => Orders::FRESCO, 'quantity' => 1]]);
        $other = IdempotencyKey::fingerprintOf([['dish_id' => Orders::FRESCO, 'quantity' => 1], ['dish_id' => Orders::CASADO, 'quantity' => 2]]);

        $this->assertSame($one, $other);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $one);
    }

    /** A different quantity or a different dish gives a different fingerprint. */
    public function test_fingerprint_changes_with_the_content(): void
    {
        $base = IdempotencyKey::fingerprintOf([['dish_id' => Orders::CASADO, 'quantity' => 2]]);

        $this->assertNotSame($base, IdempotencyKey::fingerprintOf([['dish_id' => Orders::CASADO, 'quantity' => 3]]));
        $this->assertNotSame($base, IdempotencyKey::fingerprintOf([['dish_id' => Orders::FRESCO, 'quantity' => 2]]));
    }

    /** A key repeats another only when both the key and the content match. */
    public function test_key_repeats_only_the_same_request(): void
    {
        $fingerprint = IdempotencyKey::fingerprintOf([['dish_id' => Orders::CASADO, 'quantity' => 2]]);
        $key = new IdempotencyKey('toque-1', $fingerprint);

        $this->assertTrue($key->repeats(new IdempotencyKey('toque-1', $fingerprint)));
        $this->assertFalse($key->repeats(new IdempotencyKey('toque-2', $fingerprint)));
        $this->assertFalse($key->repeats(new IdempotencyKey('toque-1', str_repeat('0', 64))));
    }

    /** A blank or too long key, or a malformed fingerprint, is refused. */
    #[DataProvider('invalidKeys')]
    public function test_invalid_key_is_refused(string $value, string $fingerprint): void
    {
        $this->expectException(InvalidValueException::class);

        new IdempotencyKey($value, $fingerprint);
    }

    /**
     * Keys that break one rule each.
     *
     * @return array<string, array{string, string}>
     */
    public static function invalidKeys(): array
    {
        return [
            'blank key' => ['  ', str_repeat('a', 64)],
            'key too long' => [str_repeat('k', 101), str_repeat('a', 64)],
            'short fingerprint' => ['toque-1', 'abc'],
        ];
    }
}
