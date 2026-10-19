<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;

final class PageTest extends TestCase
{
    /** The first page holds 15 elements unless another size is asked for. */
    public function test_request_defaults_to_the_first_page_of_fifteen(): void
    {
        $request = new PageRequest;

        $this->assertSame(1, $request->number);
        $this->assertSame(15, $request->size);
        $this->assertSame(0, $request->offset());
        $this->assertSame(30, new PageRequest(3, 15)->offset());
    }

    /** A page before the first, an empty one or one above 50 elements is rejected. */
    #[DataProvider('invalidRequests')]
    public function test_request_out_of_range_is_rejected(int $number, int $size): void
    {
        try {
            new PageRequest($number, $size);
            $this->fail('A page out of range was accepted.');
        } catch (InvalidValueException $exception) {
            $this->assertSame('shared.page_out_of_range', $exception->translationKey());
        }
    }

    /**
     * Page numbers and sizes outside the limits.
     *
     * @return array<string, array{int, int}>
     */
    public static function invalidRequests(): array
    {
        return [
            'page zero' => [0, 15],
            'empty page' => [1, 0],
            'more than fifty' => [1, 51],
        ];
    }

    /** The last page rounds up, and an empty listing still has page 1. */
    public function test_last_page_rounds_up(): void
    {
        $this->assertSame(2, new Page([], 20, new PageRequest)->lastPage());
        $this->assertSame(1, new Page([], 15, new PageRequest)->lastPage());
        $this->assertSame(1, new Page([], 0, new PageRequest)->lastPage());
    }

    /** Mapping changes the elements and keeps the total and the request. */
    public function test_map_keeps_the_page(): void
    {
        $page = new Page([1, 2], 7, new PageRequest(2, 2))->map(fn (int $n): string => "#{$n}");

        $this->assertSame(['#1', '#2'], $page->items);
        $this->assertSame(7, $page->total);
        $this->assertSame(4, $page->lastPage());
    }
}
