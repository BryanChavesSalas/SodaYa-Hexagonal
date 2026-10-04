<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Shared\Infrastructure\Http\Problem\ProblemType;

final class ProblemTypeTest extends TestCase
{
    /** Each type resolves back from its own status. */
    public function test_every_type_resolves_from_its_status(): void
    {
        foreach (ProblemType::cases() as $type) {
            $this->assertSame($type, ProblemType::fromStatus($type->status()));
        }
    }

    /** Statuses outside the catalog fall back by error class. */
    #[DataProvider('uncataloguedStatuses')]
    public function test_uncatalogued_status_falls_back_by_error_class(int $status, ProblemType $expected): void
    {
        $this->assertSame($expected, ProblemType::fromStatus($status));
    }

    /**
     * Statuses without a type of their own and the fallback they map to.
     *
     * @return array<string, array{int, ProblemType}>
     */
    public static function uncataloguedStatuses(): array
    {
        return [
            'client error' => [418, ProblemType::BadRequest],
            'server error' => [502, ProblemType::InternalError],
        ];
    }
}
