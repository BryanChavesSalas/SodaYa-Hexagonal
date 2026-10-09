<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Src\Identity\Users\Application\UseCases\AuthenticateUser;
use Src\Identity\Users\Infrastructure\Http\Requests\CreateTokenRequest;
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
        CreateTokenRequest $request,
        AuthenticateUser $authenticateUser,
    ): JsonResponse {
        $token = $authenticateUser->execute(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('device_name')->toString(),
        );

        return response()->json(
            [
                'token' => $token,
            ],
            Response::HTTP_CREATED,
        );
    }
}
