<?php

declare(strict_types=1);

namespace Src\Identity\Domain\Enums;

enum Ability: string
{
    case PlaceOrders = 'pedidos:realizar';
    case OperateKitchen = 'cocina:operar';
    case Administer = 'negocio:administrar';
}
