# ADR 0008: El cliente llega a sus pedidos por una política de lectura y por la soda del pedido

Estado: aceptada e implementada. Las políticas `<tabla>_customer_access` y las fuentes `customer` y `order` del middleware `soda` están descritas en [database.md](../database.md) y [architecture.md](../architecture.md).

## Contexto

Las rutas del cliente, `GET /api/v1/pedidos` y `/api/v1/pedidos/{pedido}`, no llevan soda en la URL, y un cliente no pertenece a ninguna. Pero `orders`, `order_lines` y `order_status_history` tienen seguridad a nivel de fila por soda: sin `app.soda_id` en la transacción no se ve ninguna fila. Un cliente tiene pedidos en varias sodas y debe ver solo los suyos; y una acción sobre un pedido, como cancelarlo o pagarlo, toca tablas de la soda del pedido: las porciones de `dishes`, el historial, los pagos.

## Decisión

Dos piezas, una en la base y otra en el borde HTTP.

En la base, cada una de las tres tablas gana una segunda política, `<tabla>_customer_access`, `FOR SELECT`, que deja ver las filas del cliente que nombra `app.customer_id`: en `orders`, por `customer_id`; en `order_lines` y `order_status_history`, por un `EXISTS` sobre `orders`. La política de aislamiento por soda no cambia. Como las políticas permisivas se combinan con `OR`, el personal sigue viendo su soda y el cliente ve lo suyo en cualquier soda; y como la política nueva solo cubre `SELECT`, el cliente no inserta, cambia ni borra nada sin la soda del pedido.

En el borde, el middleware `soda` gana dos fuentes. `soda:customer` abre la transacción sin soda y con el cliente autenticado: sirve para leer. `soda:order` además busca, como ese cliente, el pedido de la URL por el puerto `SodaOfOrder`, fija su soda en la misma transacción y la deja en la petición: todo lo que sigue corre sobre la soda del pedido, con las dos barreras de siempre. Un pedido ajeno o inexistente no es visible y responde 404 `pedido-no-encontrado`, con el mismo cuerpo en los dos casos.

## Alternativas descartadas

- **Ampliar la cláusula `USING` de la política de aislamiento con la condición del cliente.** Mezcla dos reglas en una expresión, y `RowLevelSecurityTest` dejaría de poder exigir que la política de la soda sea idéntica en toda tabla. Dos políticas se leen y se prueban por separado.
- **Leer el pedido con el rol de mantenimiento, que no pasa por las políticas.** La aplicación nunca atiende peticiones con ese rol; abrir esa puerta para una lectura es abrirla para todo.
- **Poner la soda en la URL, `/api/v1/sodas/{soda}/pedidos/{pedido}`.** Cualquiera podría nombrar la soda de otro; la soda del pedido es un hecho guardado, no un dato que el navegador deba decir.
- **Que el controlador busque el pedido del cliente y abra la transacción de la soda después.** Serían dos transacciones por petición y cada controlador repetiría la misma búsqueda; el middleware la hace una vez para todas las rutas del pedido.

## Consecuencias

- Las tablas de un pedido tienen dos políticas y las demás tablas de una soda, una; `RowLevelSecurityTest` exige exactamente eso.
- `SodaTransaction` fija el cliente junto con la soda, los dos locales a la transacción.
- El puerto `SodaOfOrder` se declara junto al middleware, en `Shared`, y lo implementa el módulo de pedidos: el núcleo compartido no conoce la tabla `orders`.
- `OrderNotFoundException` publica `pedido-no-encontrado` en toda la API, también en las rutas de la cocina.
