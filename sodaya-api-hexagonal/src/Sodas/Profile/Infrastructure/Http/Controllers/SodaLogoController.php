<?php

declare(strict_types=1);

namespace Src\Sodas\Profile\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Src\Shared\Domain\Contracts\SodaContext;
use Src\Sodas\Profile\Application\UseCases\ChangeSodaLogo;
use Src\Sodas\Profile\Infrastructure\Http\Requests\StoreSodaLogoRequest;
use Src\Sodas\Profile\Infrastructure\Http\Resources\SodaResource;

#[Group('Perfil de la soda', 'Datos públicos de la soda que administra el dueño.')]
final readonly class SodaLogoController
{
    /** Receive the port that identifies the current soda. */
    public function __construct(private SodaContext $sodaContext) {}

    /** Replace the logo of the current soda. */
    #[Endpoint(
        title: 'Subir el logo de la soda',
        description: 'Acepta JPG, PNG o WebP de hasta 2 MB y 4000 × 4000 píxeles. El tipo se lee del contenido del archivo y el servidor genera el nombre con que se guarda.',
    )]
    public function store(StoreSodaLogoRequest $request, ChangeSodaLogo $changeSodaLogo): SodaResource
    {
        return new SodaResource($changeSodaLogo->execute($request->toCommand($this->sodaContext)));
    }
}
