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
| Categoría | `Category` | `categories` | `categoria` |
| Nombre | `DishName` | `name` | `nombre` |
| Descripción | `description` | `description` | `descripcion` |
| Precio | `Price` | `price` | `precio` |
| Tiempo de preparación | `PreparationTime` | `preparation_minutes` | `minutos_preparacion` |
| Porciones disponibles | `Portions` | `available_portions` | `porciones_disponibles` |
| Plato activo | `active` | `is_active` | `activo` |
| Plato agotado | `isSoldOut()` | — | `agotado` |
| Horario | `Schedule` | `schedules` | `horario` |
| Pedido | `Order` | `orders` | `pedidos` |
| Línea del pedido | `OrderLine` | `order_lines` | `lineas` |
| Estado del pedido | `OrderStatus` | `status` | `estado` |
| Pago | `Payment` | `payments` | `pago` |
| Personal | `Staff` | — | — |
| Cocina | `Kitchen` | — | `cocina` |
| Dueño | `Owner` | — | — |
| Cliente | `Customer` | — | — |
| Visitante | `Visitor` | — | — |

Los términos de los contextos que aún no existen (pedidos, pagos, horario) quedan reservados para que se usen así cuando se construyan.

## Dinero y fechas

- Los montos son colones enteros. Nunca se usan decimales ni punto flotante.
- Las fechas se guardan con zona horaria y viajan en ISO 8601. La aplicación opera en `America/Costa_Rica`.
