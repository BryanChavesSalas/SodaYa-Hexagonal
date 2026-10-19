<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Src\Billing\Payments\Application\UseCases\PayOrder;
use Src\Billing\Payments\Infrastructure\Http\Requests\PayOrderRequest;
use Src\Billing\Payments\Infrastructure\Http\Resources\PaymentResource;
use Symfony\Component\HttpFoundation\Response;

#[Group('Pagos del cliente', 'Cobro por adelantado de los pedidos del cliente autenticado, con la pasarela en modo de prueba.')]
final readonly class OrderPaymentController
{
    /** Charge the order of the URL on behalf of the authenticated customer. */
    #[Endpoint(
        title: 'Pagar un pedido',
        description: 'Cobra un pedido propio que esté pendiente con la pasarela en modo de prueba. El monto sale de las líneas guardadas del pedido: cualquier monto enviado se ignora. Solo se envía el método de pago; en modo de prueba, `pm_card_visa` aprueba y `pm_card_visa_chargeDeclined` rechaza. Aprobado responde 201 con el pago y el pedido queda pagado; rechazado responde 402 `pago-rechazado` y el cliente puede reintentar mientras el pedido siga pendiente; si el proveedor no responde, el pago queda pendiente y la respuesta es 503 `servicio-no-disponible` con `Retry-After`. Repetir la solicitud devuelve el mismo pago: un pedido tiene a lo sumo un pago pendiente o aprobado. Un pedido ya pagado responde 409 `pedido-ya-pagado`; uno que no está pendiente, 409 `pedido-no-pagable`; uno ajeno o inexistente, 404.',
    )]
    public function store(PayOrderRequest $request, PayOrder $payOrder): JsonResponse
    {
        $payment = $payOrder->execute($request->toCommand(), now()->toDateTimeImmutable());

        return new PaymentResource($payment)
            ->response()
            ->setStatusCode($payment->isApproved() ? Response::HTTP_CREATED : Response::HTTP_ACCEPTED);
    }
}
