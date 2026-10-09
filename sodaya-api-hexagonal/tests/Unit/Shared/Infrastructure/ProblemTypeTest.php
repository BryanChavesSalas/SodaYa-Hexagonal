<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Shared\Infrastructure\Http\Problem\ProblemType;

final class ProblemTypeTest extends TestCase
{
    /** Each HTTP status resolves to its default problem type. */
    public function test_http_status_resolves_to_the_default_problem_type(): void
    {
        $this->assertSame(ProblemType::BadRequest, ProblemType::fromStatus(400));
        $this->assertSame(ProblemType::Unauthenticated, ProblemType::fromStatus(401));
        $this->assertSame(ProblemType::Forbidden, ProblemType::fromStatus(403));
        $this->assertSame(ProblemType::NotFound, ProblemType::fromStatus(404));
        $this->assertSame(ProblemType::MethodNotAllowed, ProblemType::fromStatus(405));
        $this->assertSame(ProblemType::Conflict, ProblemType::fromStatus(409));
        $this->assertSame(ProblemType::InvalidData, ProblemType::fromStatus(422));
        $this->assertSame(ProblemType::TooManyRequests, ProblemType::fromStatus(429));
        $this->assertSame(ProblemType::InternalError, ProblemType::fromStatus(500));
        $this->assertSame(ProblemType::ServiceUnavailable, ProblemType::fromStatus(503));
    }

    /** Invalid credentials shares the 401 status without replacing the default unauthenticated type. */
    public function test_invalid_credentials_uses_unauthorized_status(): void
    {
        $this->assertSame(401, ProblemType::InvalidCredentials->status());
    }

    /** Statuses outside the catalog fall back by error class. */
    #[DataProvider('uncataloguedStatuses')]
    public function test_uncatalogued_status_falls_back_by_error_class(
        int $status,
        ProblemType $expected,
    ): void {
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
