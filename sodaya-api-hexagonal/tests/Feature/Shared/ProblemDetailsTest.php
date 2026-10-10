<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Src\Shared\Domain\Exceptions\AuthenticationFailedException;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Tests\TestCase;

final class ProblemDetailsTest extends TestCase
{
    private const string CONTENT_TYPE = 'application/problem+json';

    /** Register throwaway routes that fail in each supported way. */
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api/v1/pruebas')->middleware('api')->group(function (): void {
            Route::get('fallo', fn () => throw new RuntimeException('SQLSTATE[42P01] at /var/www/secret.php'));
            Route::get('dominio', fn () => throw new InvalidValueException('catalog.price_out_of_range', ['min' => 100, 'max' => 100_000]));
            Route::post('validacion', fn (FormRequest $request) => $request->validate(['precio' => 'required|integer']));
            Route::post('credenciales', fn () => throw new class('problems.credenciales-invalidas.detail') extends AuthenticationFailedException {});
            Route::get('protegida', fn () => 'ok')->middleware('auth:sanctum');
        });
    }

    /** An unknown route answers a complete problem document. */
    public function test_unknown_route_answers_a_not_found_problem(): void
    {
        $response = $this->getJson('/api/v1/desconocido');

        $response
            ->assertNotFound()
            ->assertHeader('Content-Type', self::CONTENT_TYPE)
            ->assertExactJson([
                'type' => 'http://localhost/problemas/no-encontrado',
                'title' => 'Recurso no encontrado',
                'status' => 404,
                'detail' => 'El recurso solicitado no existe.',
                'instance' => 'urn:uuid:'.$response->headers->get('X-Request-Id'),
            ]);
    }

    /** A wrong verb answers a method-not-allowed problem. */
    public function test_wrong_verb_answers_a_method_not_allowed_problem(): void
    {
        $this->deleteJson('/api/v1')
            ->assertMethodNotAllowed()
            ->assertHeader('Content-Type', self::CONTENT_TYPE)
            ->assertJsonPath('type', 'http://localhost/problemas/metodo-no-permitido')
            ->assertJsonPath('title', 'Método no permitido');
    }

    /** Validation failures list the errors of each field in Spanish. */
    public function test_validation_failure_lists_the_errors_per_field(): void
    {
        $this->postJson('/api/v1/pruebas/validacion', ['precio' => 'caro'])
            ->assertUnprocessable()
            ->assertHeader('Content-Type', self::CONTENT_TYPE)
            ->assertJsonPath('type', 'http://localhost/problemas/datos-invalidos')
            ->assertJsonPath('detail', 'Los datos enviados no son válidos.')
            ->assertJsonPath('errores.precio.0', 'El campo precio debe ser un número entero.');
    }

    /** A domain error answers its translated message. */
    public function test_domain_error_answers_its_translated_message(): void
    {
        $this->getJson('/api/v1/pruebas/dominio')
            ->assertUnprocessable()
            ->assertJsonPath('type', 'http://localhost/problemas/datos-invalidos')
            ->assertJsonPath('detail', 'El precio debe estar entre ₡100 y ₡100000.');
    }

    /** A failed authentication answers the invalid credentials problem. */
    public function test_failed_authentication_answers_an_invalid_credentials_problem(): void
    {
        $this->postJson('/api/v1/pruebas/credenciales')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', self::CONTENT_TYPE)
            ->assertJsonPath('type', 'http://localhost/problemas/credenciales-invalidas')
            ->assertJsonPath('title', 'Credenciales inválidas')
            ->assertJsonPath('detail', 'El correo o la contraseña no son correctos.');
    }

    /** A protected route without a token answers an unauthenticated problem, even without an Accept header. */
    public function test_missing_token_answers_an_unauthenticated_problem(): void
    {
        foreach ([[], ['Accept' => 'application/json']] as $headers) {
            $this->get('/api/v1/pruebas/protegida', $headers)
                ->assertUnauthorized()
                ->assertHeader('Content-Type', self::CONTENT_TYPE)
                ->assertJsonPath('type', 'http://localhost/problemas/no-autenticado')
                ->assertJsonPath('detail', 'Debe iniciar sesión para realizar esta acción.');
        }
    }

    /** An unexpected failure never leaks technical details. */
    public function test_unexpected_failure_hides_technical_details(): void
    {
        $response = $this->getJson('/api/v1/pruebas/fallo');

        $response
            ->assertInternalServerError()
            ->assertHeader('Content-Type', self::CONTENT_TYPE)
            ->assertJsonPath('type', 'http://localhost/problemas/error-interno')
            ->assertJsonPath('detail', 'Ocurrió un error inesperado. Intente de nuevo más tarde.');

        $this->assertSame(['type', 'title', 'status', 'detail', 'instance'], array_keys($response->json()));
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
    }
}
