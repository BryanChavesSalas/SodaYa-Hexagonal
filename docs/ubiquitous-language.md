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
| Plato activo | `active` | `is_active` | `activo` |
| Plato agotado | `isSoldOut()` | — | `agotado` |
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
| Pago | `Payment` | `payments` | `pago` |
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

Los términos de los contextos que aún no existen (pedidos, pagos) quedan reservados para que se usen así cuando se construyan.

## Horario y cierres

- Una **franja** es un intervalo de atención dentro de un día de la semana. La apertura pertenece a la franja y el cierre no: una franja de 08:00 a 12:00 atiende a las 11:59 y ya no a las 12:00.
- Un día puede tener varias franjas, siempre que no se traslapen. El día 1 es lunes y el 7 es domingo (ISO 8601).
- Un **cierre excepcional** es una fecha en la que la soda no atiende aunque el horario diga lo contrario. Solo hay uno por soda y fecha.
- La soda está **abierta** en un momento si ese día no tiene un cierre excepcional y alguna franja contiene la hora. No se guarda: se calcula en cada respuesta.

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
