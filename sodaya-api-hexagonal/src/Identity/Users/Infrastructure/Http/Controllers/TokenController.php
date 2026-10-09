<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Src\Identity\Users\Application\UseCases\IssueToken;
use Src\Identity\Users\Infrastructure\Http\Requests\IssueTokenRequest;
use Src\Identity\Users\Infrastructure\Http\Resources\TokenResource;
use Symfony\Component\HttpFoundation\Response;

#[Group(
    'Autenticación',
    'Inicio de sesión del personal de una soda.',
)]
final readonly class TokenController
{
    /** Authenticate a user and issue an access token. */
    #[Endpoint(title: 'Ingresar con correo y contraseña')]
    public function store(
        IssueTokenRequest $request,
        IssueToken $issueToken,
    ): JsonResponse {
        $issuedToken = $issueToken->execute($request->toCommand());

        return new TokenResource($issuedToken)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
