# Lenguaje ubicuo y convenciones de nombres

La misma palabra significa lo mismo en la conversación con la clienta, en el contrato de la API, en el código y en la base de datos. Este documento relaciona cada término del negocio con su nombre en cada lugar.

## Convención de idioma

| Dónde | Idioma | Ejemplo |
| --- | --- | --- |
| Clases, métodos, variables y comentarios | Inglés | `Dish`, `preparationTime` |
| Tablas y columnas | Inglés, `snake_case` | `dishes.preparation_minutes` |
| Rutas de la API | Español, en plural | `/api/v1/sodas/{soda}/platos` |
| Atributos JSON | Español, `snake_case` | `minutos_preparacion` |
| Mensajes al usuario | Español, desde `lang/es` | "El campo precio es obligatorio." |

Las rutas y los atributos JSON son parte de lo que ve quien consume la API, por eso usan el vocabulario del negocio. La traducción entre ambos idiomas ocurre en un solo lugar: los Form Requests de entrada y los API Resources de salida.

`Soda` se conserva en el código porque es un término propio del dominio y no tiene un equivalente exacto en inglés.

## Glosario

| Negocio | Código | Base de datos | API |
| --- | --- | --- | --- |
| Soda | `Soda` | `sodas` | `sodas` |
| Plato | `Dish` | `dishes` | `platos` |
| Categoría | `Category` | `categories` | `categorias` |
| Nombre | `DishName`, `CategoryName` | `name` | `nombre` |
| Descripción | `description` | `description` | `descripcion` |
| Precio | `Price` | `price` | `precio` |
| Tiempo de preparación | `PreparationTime` | `preparation_minutes` | `minutos_preparacion` |
| Porciones disponibles | `Portions` | `available_portions` | `porciones_disponibles` |
| Porciones base | `basePortions` | `base_portions` | `porciones_base` |
| Plato activo | `active` | `is_active` | `activo` |
| Plato agotado | `isSoldOut()` | — | `agotado` |
| Foto del plato | `photoPath` | `photo_path` | `foto` |
| Logo de la soda | `logoPath` | `logo_path` | `logo` |
| Horario | `WeeklySchedule` | `schedules` | `horario` |
| Franja | `TimeSlot` | fila de `schedules` | `franja` |
| Día de la semana | `DayOfWeek` | `day_of_week` | `dia` |
| Hora de apertura | `opensAt` | `opens_at` | `abre` |
| Hora de cierre | `closesAt` | `closes_at` | `cierra` |
| Cierre excepcional | `Closure` | `closures` | `cierres` |
| Fecha del cierre | `ClosureDate` | `closed_on` | `fecha` |
| Motivo del cierre | `ClosureReason` | `reason` | `motivo` |
| Soda abierta | `isOpenAt()` | — | `abierta` |
| Pedido | `Order` | `orders` | `pedidos` |
| Línea del pedido | `OrderLine` | `order_lines` | `lineas` |
| Estado del pedido | `OrderStatus` | `status` | `estado` |
| Pendiente | `OrderStatus::Pending` | `pending` | `pendiente` |
| Confirmado | `OrderStatus::Confirmed` | `confirmed` | `confirmado` |
| Listo | `OrderStatus::Ready` | `ready` | `listo` |
| Entregado | `OrderStatus::Delivered` | `delivered` | `entregado` |
| Cancelado | `OrderStatus::Cancelled` | `cancelled` | `cancelado` |
| Origen de la cancelación | `CancellationOrigin` | `cancellation_origin` | `cancelado_por` |
| Plazo de pago | `paymentDeadline()` | — | — |
| Plazo de confirmación | `confirmationDeadline()` | — | — |
| Cantidad | `Quantity` | `quantity` | `cantidad` |
| Precio unitario, congelado al pedir | `unitPrice` | `unit_price` | `precio_unitario` |
| Minutos de preparación, congelados al pedir | `preparationMinutes` | `order_lines.preparation_minutes` | — |
| Subtotal de la línea | `subtotal()` | — | `subtotal` |
| Total del pedido | `total()` | — | `total` |
| Número de retiro | `PickupNumber` | `pickup_number`, `pickup_date` | `numero_retiro` |
| Contador de números de retiro | `PickupNumbers`, `CounterPickupNumbers` | `pickup_counters` | — |
| Hora estimada de retiro | `estimatedPickupAt()` | — | `retiro_estimado` |
| Mis pedidos | `ListCustomerOrders`, `GetCustomerOrder` | `app.customer_id` | `GET /api/v1/pedidos` |
| Acciones disponibles | `CustomerOrderResource` | — | `acciones`: `pagar`, `cancelar` |
| Pedido no encontrado | `OrderNotFoundException` | — | `pedido-no-encontrado` |
| Cancelación por el cliente | `CancellationOrigin::Customer` | `customer` | `POST /api/v1/pedidos/{pedido}/cancelacion`, `cancelado_por: cliente` |
| Pago | `Payment` | `payments` | `pago` |
| Estado del pago | `PaymentStatus` | `payment_status` | `pago.estado` |
| Pago aprobado | `PaymentStatus::Approved` | `approved` | `aprobado` |
| Reembolso | `RefundRequests`, `RequestRefundJob` | — | — |
| Aviso al cliente | `CustomerNotices`, `NotifyCustomerJob` | — | — |
| Trabajo en cola | `ShouldQueue` | `jobs`, `failed_jobs` | — |
| Ventas por hora | `HourlySales` | `v_hourly_sales`, `fn_hourly_sales` | `ventas-por-hora`, `horas` |
| Mínimo de pedidos | `minimumOrders` | `p_min_orders` | `minimo_pedidos` |
| Reserva de porciones | `PortionStock::reserve()` | `fn_reserve_portions` | — |
| Porciones agotadas | `PortionsSoldOutException` | — | `porciones-agotadas` |
| Pedido vacío | `OrderEmptyException` | — | `pedido-vacio` |
| Cantidad inválida | `QuantityOutOfRangeException` | — | `cantidad-invalida` |
| Plato de otra soda | `DishNotOfSodaException` | — | `plato-no-pertenece-a-la-soda` |
| Plato no disponible | `DishUnavailableException` | — | `plato-no-disponible` |
| Soda cerrada | `SodaClosedException` | — | `soda-cerrada` |
| Plato del catálogo para pedir | `OrderableDish` | — | `plato_id` |
| Clave de idempotencia | `IdempotencyKey` | `idempotency_key`, `request_fingerprint` | encabezado `Idempotency-Key` |
| Clave reutilizada | `IdempotencyKeyReusedException` | — | `conflicto` |
| Movimiento de puntos | `PointMovement` | `point_movements` | `movimientos` |
| Compra | `MovementReason::Purchase` | `purchase` | `compra` |
| Canje | `MovementReason::Redemption` | `redemption` | `canje` |
| Saldo | `balanceOf()` | suma de `points` | `saldo` |
| Puntos insuficientes | `InsufficientPointsException` | — | `puntos-insuficientes` |
| Página | `PageRequest`, `Page` | — | `pagina`, `por_pagina`, `meta` |
| Usuario | `User` | `users` | — |
| Correo | `Email` | `email` | `correo` |
| Contraseña | `PlainPassword` | `password`, solo el hash | `contrasena` |
| Token de acceso | `IssuedToken` | `personal_access_tokens` | `tokens`, `token` |
| Dispositivo | `deviceName` | `personal_access_tokens.name` | `dispositivo` |
| Ability | `Role::abilities()` | `personal_access_tokens.abilities` | `abilities` |
| Rol | `Role` | `role` | — |
| Personal | `Role::isStaff()` | `kitchen`, `owner` | — |
| Cocina | `Role::Kitchen` | `kitchen` | `cocina` |
| Dueño | `Role::Owner` | `owner` | — |
| Cliente | `Role::Customer` | `customer` | — |
| Visitante | `Visitor` | — | — |

Los términos de los contextos que aún no existen (pagos) quedan reservados para que se usen así cuando se construyan.

## Porciones

- Las **porciones base** son las que la soda prepara en un día normal. Las **porciones disponibles** son las que quedan hoy: bajan con cada venta y suben cuando un pedido se cancela.
- Cambiar las porciones base no toca las disponibles del día en curso.
- Cada mañana, a las 5:00, el **restablecimiento diario** devuelve las porciones disponibles de cada plato a su base.
- Hacer un pedido **reserva** sus porciones: se descuentan todas o ninguna. Si un plato no alcanza, el pedido no se crea y la respuesta es **porciones agotadas**.

## Horario y cierres

- Una **franja** es un intervalo de atención dentro de un día de la semana. La apertura pertenece a la franja y el cierre no: una franja de 08:00 a 12:00 atiende a las 11:59 y ya no a las 12:00.
- Un día puede tener varias franjas, siempre que no se traslapen. El día 1 es lunes y el 7 es domingo (ISO 8601).
- Un **cierre excepcional** es una fecha en la que la soda no atiende aunque el horario diga lo contrario. Solo hay uno por soda y fecha.
- La soda está **abierta** en un momento si ese día no tiene un cierre excepcional y alguna franja contiene la hora. No se guarda: se calcula en cada respuesta.

## Pedidos

- Un **pedido** pertenece a una soda y a un cliente, y tiene de 1 a 20 líneas, una por plato, con cantidades de 1 a 20. Sin líneas es un **pedido vacío**; con una cantidad fuera del rango, una **cantidad inválida**.
- Solo se pide en una soda **abierta** y solo platos **activos** de esa soda: un plato de otra soda se trata como un plato que no existe.
- Cada solicitud de pedido lleva una **clave de idempotencia** elegida por el cliente. Repetirla con las mismas líneas devuelve el pedido ya hecho; repetirla con otras líneas es un **conflicto**.
- La **línea** congela el nombre, el precio y los **minutos de preparación** que tenía el plato al pedirlo: un cambio posterior del menú no altera un pedido hecho ni su hora estimada.
- Cada pedido recibe un **número de retiro**: un número corto, de 1 a 9999, que el mostrador dice en voz alta para entregarlo. Es consecutivo por soda y por día de Costa Rica: el primer pedido de cada día es el 1. Lo entrega el **contador** de la soda y el día; el identificador largo del pedido sigue siendo el UUID.
- La **hora estimada de retiro** es el momento en que se hizo el pedido más el mayor tiempo de preparación de sus platos. No se guarda: se calcula a partir de las líneas congeladas.
- El cliente consulta **sus pedidos** en cualquier soda, del más reciente al más antiguo, y el detalle de cada uno. Un pedido ajeno es un **pedido no encontrado**, igual que uno que no existe.
- Cada pedido del cliente trae sus **acciones disponibles**: **pagar** mientras está pendiente y sin pago aprobado, **cancelar** mientras está pendiente o confirmado. Un pedido listo, entregado o cancelado no tiene acciones.
- El cliente **cancela su pedido** mientras está pendiente o confirmado; la cancelación queda registrada con el origen **cliente**, las porciones vuelven al menú y, si ya había pagado, recibe el **reembolso**. Nadie le avisa de una cancelación que hizo él mismo.
- El pedido avanza en un solo sentido: **pendiente**, **confirmado**, **listo** y **entregado**. Se puede **cancelar** mientras está pendiente o confirmado, y la cancelación registra quién la originó: el cliente, la cocina, la falta de pago o la falta de confirmación.
- Entregado y cancelado son estados finales.
- Un pedido tiene 10 minutos para que se apruebe su **pago**. Si al cumplirse el **plazo de pago** sigue pendiente y sin pago, se cancela por **falta de pago**: sus porciones vuelven al menú y el cliente recibe un aviso.
- Con el pago aprobado, la cocina tiene 10 minutos para **confirmar** el pedido. Si al cumplirse el **plazo de confirmación** nadie lo confirmó, se cancela por **falta de confirmación** y el cliente recibe el **reembolso**.
- La cocina solo **confirma** un pedido con el **pago aprobado**. Mientras el pago esté pendiente, el pedido espera.
- Cancelar un pedido pagado genera un **reembolso**: se le devuelve al cliente lo que pagó.

## Puntos

- Los **puntos** se llevan en un **libro de movimientos**. Una **compra** agrega puntos y un **canje** los quita; el **saldo** es la suma de los movimientos.
- Un movimiento no se corrige ni se borra: un error se compensa con otro movimiento.
- Un pedido **entregado** acredita 1 punto por cada ₡1000 completos del total de sus líneas, sin la propina. Un pedido de ₡6600 acredita 6 puntos; uno de ₡999, ninguno.
- Un pedido acredita sus puntos una sola vez.
- Un **canje** nunca supera el saldo; si lo supera, la respuesta es **puntos insuficientes**.

## Roles

La lista de roles es cerrada. Cada rol define a qué soda pertenece la cuenta y qué abilities llevan sus tokens.

| Rol | Valor en la base | Soda | Abilities del token |
| --- | --- | --- | --- |
| Cliente | `customer` | Ninguna | `pedidos` |
| Cocina | `kitchen` | Exactamente una | `cocina` |
| Dueño | `owner` | Exactamente una | `cocina`, `administrar` |

El **personal** es la cocina y el dueño: siempre pertenece a una soda. Un cliente nunca pertenece a una.

Las abilities determinan qué operaciones puede realizar cada miembro del personal:

- `cocina`: permite consultar los recursos del área de cocina, como platos, categorías, horario y cierres.
- `administrar`: permite crear, editar o eliminar recursos administrativos de la soda.

El rol de cocina recibe únicamente la ability `cocina`, por lo que puede consultar la información pero no modificarla. El dueño recibe las abilities `cocina` y `administrar`, por lo que puede consultar y también realizar operaciones de creación, edición y eliminación.

## Dinero y fechas

- Los montos son colones enteros. Nunca se usan decimales ni punto flotante.
- Las fechas se guardan con zona horaria y viajan en ISO 8601. La aplicación opera en `America/Costa_Rica`.
- Las horas del horario viajan como `HH:MM` y las fechas de los cierres como `AAAA-MM-DD`; ambas se leen en hora de Costa Rica.
