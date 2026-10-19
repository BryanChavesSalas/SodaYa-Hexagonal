<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Http\Problem;

enum ProblemType: string
{
    case BadRequest = 'solicitud-invalida';
    case Unauthenticated = 'no-autenticado';
    case InvalidCredentials = 'credenciales-invalidas';
    case Forbidden = 'prohibido';
    case NotFound = 'no-encontrado';
    case OrderNotFound = 'pedido-no-encontrado';
    case MethodNotAllowed = 'metodo-no-permitido';
    case Conflict = 'conflicto';
    case TransitionNotAllowed = 'transicion-no-permitida';
    case OrderNotPaid = 'pedido-sin-pago';
    case PortionsSoldOut = 'porciones-agotadas';
    case InsufficientPoints = 'puntos-insuficientes';
    case OrderAlreadyPaid = 'pedido-ya-pagado';
    case OrderNotPayable = 'pedido-no-pagable';
    case PaymentRejected = 'pago-rechazado';
    case DishUnavailable = 'plato-no-disponible';
    case SodaClosed = 'soda-cerrada';
    case InvalidData = 'datos-invalidos';
    case OrderEmpty = 'pedido-vacio';
    case QuantityInvalid = 'cantidad-invalida';
    case DishNotOfSoda = 'plato-no-pertenece-a-la-soda';
    case TooManyRequests = 'demasiadas-solicitudes';
    case InternalError = 'error-interno';
    case ServiceUnavailable = 'servicio-no-disponible';

    /** HTTP status the problem type answers with. */
    public function status(): int
    {
        return match ($this) {
            self::BadRequest => 400,
            self::Unauthenticated, self::InvalidCredentials => 401,
            self::Forbidden => 403,
            self::NotFound, self::OrderNotFound => 404,
            self::PaymentRejected => 402,
            self::MethodNotAllowed => 405,
            self::Conflict, self::TransitionNotAllowed, self::OrderNotPaid, self::PortionsSoldOut, self::InsufficientPoints, self::OrderAlreadyPaid, self::OrderNotPayable, self::DishUnavailable, self::SodaClosed => 409,
            self::InvalidData, self::OrderEmpty, self::QuantityInvalid, self::DishNotOfSoda => 422,
            self::TooManyRequests => 429,
            self::InternalError => 500,
            self::ServiceUnavailable => 503,
        };
    }

    /** Resolve the first type of an HTTP status, defaulting by error class. */
    public static function fromStatus(int $status): self
    {
        return array_find(self::cases(), fn (self $type): bool => $type->status() === $status)
            ?? ($status >= 500 ? self::InternalError : self::BadRequest);
    }
}
