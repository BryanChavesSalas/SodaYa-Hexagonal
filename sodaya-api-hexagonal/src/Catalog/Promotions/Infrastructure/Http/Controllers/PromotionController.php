<?php

declare(strict_types=1);

namespace Src\Catalog\Promotions\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Catalog\Promotions\Application\UseCases\DefinePromotion;
use Src\Catalog\Promotions\Application\UseCases\DeletePromotion;
use Src\Catalog\Promotions\Application\UseCases\ListPromotions;
use Src\Catalog\Promotions\Infrastructure\Http\Requests\StorePromotionRequest;
use Src\Catalog\Promotions\Infrastructure\Http\Resources\PromotionResource;
use Src\Shared\Domain\Contracts\SodaContext;
use Src\Shared\Infrastructure\Http\Requests\ListRequest;
use Src\Shared\Infrastructure\Http\Resources\PageMeta;

#[Group('Promociones de la soda', 'Horas felices: un descuento para toda la soda por día de la semana y franja horaria, solo para el dueño.')]
final readonly class PromotionController
{
    /** Receive the port that identifies the current soda. */
    public function __construct(private SodaContext $sodaContext) {}

    /** List the happy hours of the current soda. */
    #[Endpoint(title: 'Consultar las promociones de la soda', description: 'Ordenadas por día y hora de inicio; el día 1 es lunes y el 7 es domingo.')]
    public function index(ListRequest $request, ListPromotions $listPromotions): AnonymousResourceCollection
    {
        $page = $listPromotions->execute($this->sodaContext->current()->value, $request->page());

        return PromotionResource::collection($page->items)->additional(PageMeta::of($page));
    }

    /** Define a happy hour of the current soda. */
    #[Endpoint(
        title: 'Definir una promoción',
        description: 'El descuento va de 1 % a 90 % y aplica a todos los platos de la soda desde `inicio`, incluido, hasta `fin`, excluido. Responde 409 `promocion-traslapada` si comparte un minuto con otra promoción del mismo día.',
    )]
    public function store(StorePromotionRequest $request, DefinePromotion $definePromotion): JsonResponse
    {
        return new PromotionResource($definePromotion->execute($request->toCommand($this->sodaContext)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /** Remove a happy hour of the current soda. */
    #[Endpoint(title: 'Eliminar una promoción')]
    public function destroy(string $promocion, DeletePromotion $deletePromotion): Response
    {
        $deletePromotion->execute($promocion, $this->sodaContext->current()->value);

        return response()->noContent();
    }
}
