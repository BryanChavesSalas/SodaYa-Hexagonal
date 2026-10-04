<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Http\Exception;

use Illuminate\Http\JsonResponse;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\Shared\Domain\Exceptions\NotFoundException;
use Symfony\Component\HttpFoundation\Response;

final readonly class DomainExceptionRenderer
{
    /** Render a domain exception with its translated message. */
    public function __invoke(DomainException $exception): JsonResponse
    {
        $status = $exception instanceof NotFoundException
            ? Response::HTTP_NOT_FOUND
            : Response::HTTP_UNPROCESSABLE_ENTITY;

        return new JsonResponse(
            ['message' => __($exception->translationKey(), $exception->parameters())],
            $status,
        );
    }
}
