<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Identity\Users\Application\UseCases\IssueToken;
use Src\Identity\Users\Infrastructure\Http\Requests\IssueTokenRequest;
use Src\Identity\Users\Infrastructure\Http\Resources\TokenResource;

#[Group('Sesiones', 'Ingreso con correo y contraseña mediante tokens de acceso.')]
final readonly class TokenController
{
    /** Log in with email and password and answer a bearer token. */
    #[Endpoint(
        title: 'Ingresar con correo y contraseña',
        description: 'Devuelve un token Bearer con las abilities del rol, que se envía en el encabezado `Authorization`. Responde 401 `credenciales-invalidas` si el correo no existe, la contraseña no coincide o la cuenta está desactivada, sin indicar cuál dato falló.',
    )]
    public function store(IssueTokenRequest $request, IssueToken $issueToken): JsonResponse
    {
        return new TokenResource($issueToken->execute($request->toCommand()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
