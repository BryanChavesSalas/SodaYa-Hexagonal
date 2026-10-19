<?php

declare(strict_types=1);

namespace Tests\Unit\Ordering\Orders\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Ordering\Orders\Domain\ValueObjects\OrderStatus;

final class OrderStatusTest extends TestCase
{
    /**
     * The transition table of an order: every pair of statuses and whether the change is allowed.
     */
    #[DataProvider('transitions')]
    public function test_only_the_transitions_of_the_table_are_allowed(OrderStatus $from, OrderStatus $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canChangeTo($to));
    }

    /**
     * Every pair of statuses, 25 in total; five of them are allowed.
     *
     * @return iterable<string, array{OrderStatus, OrderStatus, bool}>
     */
    public static function transitions(): iterable
    {
        $allowed = [
            'pending → confirmed',
            'pending → cancelled',
            'confirmed → ready',
            'confirmed → cancelled',
            'ready → delivered',
        ];

        foreach (OrderStatus::cases() as $from) {
            foreach (OrderStatus::cases() as $to) {
                $name = "{$from->value} → {$to->value}";

                yield $name => [$from, $to, in_array($name, $allowed, true)];
            }
        }
    }

    /** Delivered and cancelled are final: nothing follows them. */
    public function test_final_statuses_have_no_next(): void
    {
        $this->assertNull(OrderStatus::Delivered->next());
        $this->assertNull(OrderStatus::Cancelled->next());
        $this->assertFalse(OrderStatus::Ready->isCancellable());
    }
}
