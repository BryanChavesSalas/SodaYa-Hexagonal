<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class OpenApiContractTest extends TestCase
{
    private const string CONTRACT_PATH = 'openapi/v1.json';

    /** The versioned contract matches the one generated from the code. */
    public function test_versioned_contract_matches_the_code(): void
    {
        $generatedPath = storage_path('framework/testing/openapi.json');

        Artisan::call('scramble:export', ['--path' => $generatedPath]);

        $this->assertSame(
            File::json(base_path(self::CONTRACT_PATH)),
            File::json($generatedPath),
            'The contract is outdated. Run "composer openapi" and commit the result.',
        );
    }

    /** Error responses are documented as problem documents. */
    public function test_contract_documents_errors_as_problem_documents(): void
    {
        $contract = File::json(base_path(self::CONTRACT_PATH));

        $this->assertSame('3.1.0', $contract['openapi']);

        foreach ($contract['components']['responses'] as $response) {
            $this->assertSame(['application/problem+json'], array_keys($response['content']));
        }
    }

    /** Staff operations document their required abilities and forbidden response. */
    public function test_staff_operations_document_required_abilities(): void
    {
        $contract = File::json(base_path(self::CONTRACT_PATH));

        $expected = [
            'get /cocina/categorias' => ['cocina'],
            'post /cocina/categorias' => ['administrar'],
            'patch /cocina/categorias/{categoria}' => ['administrar'],
            'delete /cocina/categorias/{categoria}' => ['administrar'],

            'get /cocina/cierres' => ['cocina'],
            'post /cocina/cierres' => ['administrar'],
            'post /cocina/cierres/hoy' => ['administrar'],
            'delete /cocina/cierres/{cierre}' => ['administrar'],

            'get /cocina/horario' => ['cocina'],
            'post /cocina/horario' => ['administrar'],
            'delete /cocina/horario/{franja}' => ['administrar'],

            'get /cocina/platos' => ['cocina'],
            'post /cocina/platos' => ['administrar'],
            'patch /cocina/platos/{plato}' => ['administrar'],
        ];

        foreach ($expected as $operation => $abilities) {
            [$method, $path] = explode(' ', $operation, 2);

            $documentedOperation = $contract['paths'][$path][$method];

            $this->assertSame(
                $abilities,
                $documentedOperation['x-abilities'],
            );

            $this->assertSame(
                '#/components/responses/Forbidden',
                $documentedOperation['responses']['403']['$ref'],
            );
        }
    }

    /** The interactive documentation and its document are published. */
    public function test_interactive_documentation_is_published(): void
    {
        $this->get('/docs/api')->assertOk();
        $this->getJson('/docs/api.json')->assertOk()->assertJsonPath('info.title', 'SodaYa API');
    }
}
