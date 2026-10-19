# Base de datos: PostgreSQL 18

SodaYa usa PostgreSQL 18 como único motor, en desarrollo, en pruebas y en producción. Este documento fija cómo se conecta la aplicación y cómo se crean y versionan los objetos de la base.

## Versión

La versión mínima es PostgreSQL 18. Una prueba falla si el servidor es anterior, porque el proyecto usa funciones que no existen antes de esa versión, como `uuidv7()`.

## Roles

La aplicación nunca se conecta con el rol dueño de las tablas.

| Rol            | Conexión de Laravel   | Qué hace                                        | Qué no puede hacer                                                      |
| -------------- | --------------------- | ----------------------------------------------- | ----------------------------------------------------------------------- |
| `sodaya_owner` | `pgsql_admin`         | Es dueño de las bases y ejecuta las migraciones | No atiende peticiones                                                   |
| `sodaya_app`   | `pgsql` (por defecto) | Lee y escribe filas                             | Crear, alterar o borrar tablas; no es superusuario ni tiene `BYPASSRLS` |
| `sodaya_maintenance` | `pgsql_maintenance` | Ejecuta las tareas por lotes que recorren todas las sodas | Cambiar el esquema; solo tiene los permisos que cada migración le otorga |

El script `database/roles.sql` crea los tres roles y las dos bases. Lo ejecuta una sola vez un superusuario, con las contraseñas como variables de `psql`: nunca se versionan.

La primera migración otorga al rol de la aplicación, con `ALTER DEFAULT PRIVILEGES`, los permisos de lectura y escritura sobre toda tabla y secuencia que el dueño cree después. Ninguna tabla nueva necesita un paso manual.

Por qué importa: en PostgreSQL los superusuarios y los roles con `BYPASSRLS` no pasan por las políticas de seguridad a nivel de fila, y el dueño de una tabla tampoco, salvo que la tabla las fuerce. Las tablas de una soda las fuerzan, pero la aplicación igual se conecta con un rol que no es dueño ni tiene `BYPASSRLS`: el aislamiento no depende de una sola línea de una migración.

`sodaya_maintenance` es el único rol con `BYPASSRLS`. Lo necesita una tarea que recorre todas las sodas, como el restablecimiento diario de las porciones: con el rol de la aplicación tendría que fijar una soda por transacción y recorrerlas desde PHP. A cambio, no recibe permisos por defecto: cada migración le otorga solo lo que su tarea usa, a veces columna por columna, y la aplicación nunca atiende peticiones con él.

Entre esos permisos está `MAINTAIN`, el privilegio que PostgreSQL 17 agregó para las tareas de mantenimiento de una relación: `VACUUM`, `ANALYZE`, `REINDEX`, `CLUSTER` y `REFRESH MATERIALIZED VIEW`. Antes solo podía refrescar una vista materializada su dueño; con `MAINTAIN` el rol de mantenimiento la refresca sin ser dueño de nada y sin poder leerla ni cambiarla. La sección «Resumen diario de ventas» lo usa.

## Migraciones

- Se ejecutan siempre con el rol dueño: `php artisan migrate --database=pgsql_admin`.
- Cada migración tiene su `down()` y una prueba las ejecuta hacia atrás y hacia adelante.
- Un cambio que rompe la versión anterior del código se hace en dos despliegues: primero se expande el esquema y, cuando el código viejo ya no corre, se contrae.
- Un índice sobre una tabla que crece, como `orders`, se crea con `CREATE INDEX CONCURRENTLY`: no bloquea las escrituras mientras se construye. Esa instrucción no puede correr dentro de una transacción, así que la migración declara `public $withinTransaction = false;`.

## Índices de los listados

Cada consulta frecuente tiene un índice que cubre su filtro y su orden. Una prueba carga 10 000 pedidos y revisa el plan con `EXPLAIN ANALYZE`: si el índice desaparece o la consulta cambia de forma, la prueba falla.

| Consulta | Índice |
| --- | --- |
| Ventas por hora: pedidos entregados de una soda en un rango de `created_at` | `orders_soda_id_status_created_at_index` |
| Ventas diarias: días de una soda en un rango de fechas, sobre el resumen precalculado | `mv_daily_sales_soda_id_day_unique`, el índice único que además exige el refresco concurrente |
| Lista de cocina: pedidos de una soda en un estado, del más antiguo al más nuevo | `orders_soda_id_status_created_at_index`, que además entrega las filas ya ordenadas |
| Platos y categorías de una soda por nombre | Las llaves únicas `(soda_id, name)` |
| Cierres de una soda desde una fecha | La llave única `(soda_id, closed_on)` |

El orden de las columnas importa: primero las que se comparan con `=` (`soda_id`, `status`) y al final la del rango o del orden (`created_at`). La lista de cocina, `GET /api/v1/cocina/pedidos?estado=...`, conserva esa forma de consulta: el repositorio ordena solo por `created_at`, sin desempate por `id`, porque un desempate obligaría a un ordenamiento incremental después del índice. `OrderIndexesTest` revisa el plan de la consulta que el repositorio ejecuta.

## Convenciones del esquema

| Tema                           | Convención                                                           |
| ------------------------------ | -------------------------------------------------------------------- |
| Nombres                        | Inglés, `snake_case`; tablas en plural                               |
| Llave primaria                 | `id` de tipo `uuid`, versión 7, con `DEFAULT uuidv7()`               |
| Soda                           | Toda tabla que pertenece a una soda lleva `soda_id`                  |
| Llaves únicas y foráneas       | Incluyen `soda_id`, para que el aislamiento no dependa del código    |
| Fechas                         | `timestamptz`; la sesión trabaja en `America/Costa_Rica`             |
| Dinero                         | Colones enteros en `integer`; nunca decimales ni punto flotante      |
| Rangos del negocio             | Restricción `CHECK` con nombre `<tabla>_<columna>_check`             |
| Intervalos que no se traslapan | Restricción `EXCLUDE` con nombre `<tabla>_no_overlap_excl`           |
| Normalización                  | Tercera forma normal; cada excepción se justifica en `data-model.md` |

La aplicación genera el identificador antes de guardar, así el agregado tiene identidad desde que nace. El `DEFAULT uuidv7()` es el respaldo para las filas que no pasan por la aplicación.

## Objetos programables

El constructor de esquemas de Laravel no cubre funciones, procedimientos, triggers, políticas ni vistas. Se crean dentro de una migración con `DB::statement()` o `DB::unprepared()`, y su `down()` los elimina.

Las funciones y los procedimientos se crean con `CREATE OR REPLACE`: `migrate:fresh` borra las tablas, y con ellas sus triggers y vistas, pero no las funciones, y la siguiente ejecución fallaría al crearlas de nuevo.

| Objeto              | Prefijo                  | Regla                                                                                     |
| ------------------- | ------------------------ | ----------------------------------------------------------------------------------------- |
| Función             | `fn_`                    | `SECURITY INVOKER` y `search_path` fijo; la única `SECURITY DEFINER` es `fn_daily_sales_of_every_soda`, que pertenece al rol de mantenimiento y solo puede ejecutar el dueño del esquema |
| Procedimiento       | `sp_`                    | Solo para tareas por lotes; se invoca con `CALL` fuera de una transacción                 |
| Trigger             | `trg_`                   | `BEFORE` por fila para validar o completar; `AFTER` por fila para propagar a otras tablas |
| Vista               | `v_`                     | `security_invoker = true`, para que apliquen las políticas de quien consulta; sobre una vista materializada, que no tiene políticas, `security_barrier = true` y el filtro por la soda de la transacción en la propia vista |
| Vista materializada | `mv_`                    | Índice único para refrescar con `CONCURRENTLY`; el rol de la aplicación no la lee: consulta su vista `v_`; la refresca el rol de mantenimiento con `MAINTAIN`, y la hora del último refresco queda en `materialized_view_refreshes` |
| Política            | `<tabla>_soda_isolation` | Una por tabla de una soda, con la misma expresión en `USING` y `WITH CHECK`               |
| Política de lectura | `<tabla>_customer_access` | Solo en las tablas de un pedido, `FOR SELECT`: el cliente lee lo suyo sin soda          |

### Historial de estados del pedido

El trigger `trg_orders_status_history` corre `AFTER UPDATE OF status` por cada fila de `orders` cuyo estado cambia, y la función `fn_record_order_status_change()` agrega a `order_status_history` el estado anterior, el nuevo y la hora. Es un trigger `AFTER` porque propaga el cambio a otra tabla, como recomienda la documentación de PostgreSQL; uno `BEFORE` se usa para validar o completar la misma fila.

La función es `SECURITY INVOKER`: inserta con el rol de quien cambia el pedido, y la política de la tabla exige que la fila sea de la soda en curso. El rol de la aplicación puede insertar y leer el historial, pero no actualizarlo, borrarlo ni vaciarlo: la migración le revoca `UPDATE`, `DELETE` y `TRUNCATE` sobre esa tabla. Así el historial registra también los cambios hechos con SQL directo, y la aplicación no puede reescribirlo.

El dominio sigue decidiendo qué transición es válida; el trigger solo deja constancia.

### Restablecimiento diario de las porciones

El procedimiento `sp_reset_daily_portions()` copia `base_portions` sobre `available_portions` en cada plato cuyas porciones difieren de la base. Recorre las sodas una por una y confirma con `COMMIT` después de cada una: así nunca bloquea los platos de todas las sodas a la vez, y si se interrumpe, las sodas ya procesadas quedan listas.

Un procedimiento que hace `COMMIT` tiene dos condiciones. Se invoca con `CALL` fuera de una transacción, porque dentro de una falla con el SQLSTATE `2D000`. Y no puede tener cláusulas `SET`, como `SET search_path`, por eso nombra sus tablas con el esquema, `public.dishes`.

La migración le quita el permiso de ejecutarlo a `PUBLIC` y se lo da solo a `sodaya_maintenance`, junto con `SELECT` sobre `sodas` y `dishes` y `UPDATE` sobre las columnas `available_portions` y `updated_at`.

### Libro de puntos

`point_movements` es inmutable por dos caminos. El rol de la aplicación no tiene `UPDATE`, `DELETE` ni `TRUNCATE` sobre la tabla: la migración se los revoca. Y el trigger `trg_point_movements_immutable`, `BEFORE UPDATE OR DELETE` por fila, rechaza el cambio con su mensaje aunque lo intente el dueño de la tabla. Un error en los puntos se corrige con un movimiento nuevo, nunca editando uno viejo.

Dos canjes simultáneos del mismo cliente se ordenan con un bloqueo consultivo transaccional, `pg_advisory_xact_lock`, con una clave derivada del cliente. Es la herramienta cuando lo que hay que proteger no es una fila sino un cálculo, aquí la suma de los movimientos: `FOR UPDATE` no tiene a qué aplicarse, porque el canje que se está por escribir todavía no existe. La prueba `RedeemPointsRaceTest` lo demuestra con dos procesos, igual que la de la última porción.

Es un trigger `BEFORE` porque impide el cambio de la propia fila; el del historial de pedidos es `AFTER` porque propaga un cambio a otra tabla.

### Pedidos vencidos

La migración `let_the_maintenance_role_find_expired_orders` le da a `sodaya_maintenance` permiso de leer `orders` y nada más, y crea el índice parcial `orders_pending_created_at_index` sobre `created_at` de los pedidos pendientes. El comando que corre cada minuto busca solo pedidos pendientes, que son pocos; un índice parcial guarda solo esas filas y se mantiene pequeño aunque la tabla tenga años de pedidos entregados.

### Contador de números de retiro

`pickup_counters` tiene una fila por soda y día con el último número entregado. `CounterPickupNumbers` la avanza con una sola instrucción:

```sql
INSERT INTO pickup_counters (soda_id, pickup_date, last_number)
VALUES (?, ?, 1)
ON CONFLICT (soda_id, pickup_date)
DO UPDATE SET last_number = pickup_counters.last_number + 1
RETURNING last_number
```

Si la fila no existe, la crea en 1; si existe, la suma. Como el `UPDATE` bloquea la fila hasta el final de la transacción, dos pedidos de la misma soda en el mismo instante se ordenan: el segundo espera y recibe el número siguiente. Una secuencia de PostgreSQL no serviría, porque no se reinicia por soda ni por día y no se deshace con la transacción; el contador sí, así que un pedido que no se llega a crear no gasta número. La tabla es de una soda, con su política de seguridad a nivel de fila, igual que `orders`.

La migración que la creó numeró los pedidos que ya existían con `row_number()` por soda y día de Costa Rica, ordenados por su creación, y dejó cada contador en el máximo de su día. Para ese relleno quitó y volvió a poner `FORCE ROW LEVEL SECURITY` dentro de su transacción, como hizo la de `base_portions`.

### Reserva de porciones

`fn_reserve_portions(p_soda_id, p_dish_ids, p_quantities)` descuenta las porciones de todas las líneas de un pedido o de ninguna, y responde si reservó. Primero bloquea con `SELECT ... FOR UPDATE` los platos del pedido en el orden de sus identificadores; después revisa que todos existan en la soda, estén activos y tengan porciones suficientes; solo entonces descuenta. Si algo falta, devuelve `false` sin haber cambiado nada.

Dos pedidos que piden la última porción a la vez se ordenan en el bloqueo: el segundo espera a que el primero confirme, vuelve a leer la fila ya descontada y recibe `false`. El orden fijo de los bloqueos evita el interbloqueo: si un pedido bloqueara el casado y luego el fresco y otro lo hiciera al revés, cada uno esperaría al otro. La devolución de porciones al cancelar recorre los platos en el mismo orden. El `CHECK` de `available_portions` sigue como última barrera: ninguna fila puede quedar en negativo.

La prueba `LastPortionRaceTest` lo demuestra con dos procesos de PHP de verdad. Los dos esperan detrás de un bloqueo consultivo (`pg_advisory_lock`) que tiene la prueba; cuando ambos están esperando, la prueba lo suelta y los dos piden la última porción en el mismo instante.

### Ventas por hora

La vista `v_hourly_sales` tiene una fila por pedido entregado: su soda, su identificador, el momento en que se creó, la hora de Costa Rica de ese momento y la suma de sus líneas. No incluye propinas: el total sale de las líneas. Es `security_invoker = true`, así que la consulta corre con el rol de quien la hace y las políticas de `orders` y `order_lines` siguen filtrando por la soda en curso. Sin esa opción, una vista corre con los permisos de su dueño y podría saltarse la seguridad a nivel de fila.

La función `fn_hourly_sales(p_soda_id, p_date, p_min_orders)` agrupa esas filas por hora y descarta las horas con menos pedidos que el mínimo. Filtra la fecha con un rango sobre `created_at`, desde la medianoche de Costa Rica de esa fecha hasta la del día siguiente, y no con una expresión sobre la columna: así PostgreSQL puede usar un índice sobre `created_at`. La zona horaria se escribe en la función porque el negocio cuenta las horas de Costa Rica, sin importar la zona de la sesión que consulte.

`fn_hourly_sales` es la única función sin `search_path` fijo, y por eso nombra su vista como `public.v_hourly_sales`. PostgreSQL puede expandir una función SQL de una sola consulta dentro de la consulta que la llama, como si fuera una vista, pero solo si la función no tiene cláusulas `SET`. Expandida, el planificador ve el filtro por soda y por fecha, y puede elegir un índice; `EXPLAIN` muestra el plan completo y no una caja negra.

La vista también se crea con `CREATE OR REPLACE`. Borrar `orders` con `migrate:fresh` la borra en cascada, porque depende de esa tabla.

### Resumen diario de ventas

El reporte de ventas por día compara meses: sumar las líneas de cada pedido entregado de un año en cada consulta sería caro y siempre daría lo mismo. Por eso la vista materializada `mv_daily_sales` guarda el resultado, una fila por soda y día de Costa Rica con los pedidos entregados y la suma de `unit_price × quantity` de sus líneas, sin propinas, y la consulta del reporte la lee en lugar de calcular. Una prueba inspecciona el plan con `EXPLAIN` y falla si aparece `orders` u `order_lines`.

Una vista materializada es una tabla: no se actualiza sola y no admite políticas de seguridad a nivel de fila. Las dos cosas se resuelven con el rol de mantenimiento y con una vista.

**El refresco.** `sodaya:refresh-daily-sales` ejecuta `REFRESH MATERIALIZED VIEW CONCURRENTLY mv_daily_sales` con `sodaya_maintenance`, al que la migración le otorga `MAINTAIN` sobre la vista. `CONCURRENTLY` calcula el resultado nuevo en una tabla temporal y lo aplica fila por fila, con un bloqueo que no detiene a quien lee; a cambio exige un índice único sin `WHERE` sobre la vista, `mv_daily_sales_soda_id_day_unique`, que además sirve a la consulta del reporte. Una prueba abre un refresco sin confirmarlo y lee el resumen desde otra conexión con `lock_timeout`: sin `CONCURRENTLY`, la lectura esperaría.

PostgreSQL no guarda cuándo se refrescó una vista materializada, y `pg_stat_user_tables` no sirve para eso, así que el adaptador escribe el momento en la tabla `materialized_view_refreshes`, en la misma transacción que el refresco: si el refresco se deshace, la hora también. Solo el rol de mantenimiento puede escribir esa tabla; el rol de la aplicación la lee para responder `actualizado_en`.

**Quién calcula las filas.** `REFRESH MATERIALIZED VIEW` corre con el usuario dueño de la vista, no con el que ordena el refresco, y `sodaya_owner` está sujeto a las políticas forzadas de `orders` y `order_lines`: una vista definida directamente sobre esas tablas quedaba vacía aunque la refrescara el rol de mantenimiento, porque sin soda fijada ninguna política deja pasar una fila. La solución es que las filas las lea el único rol que ve todas las sodas. `fn_daily_sales_of_every_soda()` es una función `SECURITY DEFINER` que pertenece a `sodaya_maintenance`: corre con los permisos de su dueño, con `BYPASSRLS`, y devuelve la agrupación de todas las sodas; la vista materializada es `SELECT ... FROM fn_daily_sales_of_every_soda()`. Una función así es una puerta y se vigila: la migración le quita `EXECUTE` a `PUBLIC` y se lo da solo al dueño del esquema, que es quien ejecuta el refresco por dentro. El rol de la aplicación recibe `42501` si la llama.

Como la función debe pertenecer al rol de mantenimiento, la crea ese rol desde la propia migración, con la conexión `pgsql_maintenance`, después de que el dueño le otorga `CREATE` sobre el esquema por un momento y antes de que se lo quite. Esa migración declara `$withinTransaction = false`: lo que una conexión otorga tiene que estar confirmado para que la otra lo use. El cuerpo de la función va en `BEGIN ATOMIC ... END` en lugar de una cadena `$$ ... $$`, así PostgreSQL registra que depende de `orders` y `order_lines`, y `migrate:fresh`, que borra las tablas en cascada, se lleva la función y la vista materializada con ellas.

**Quién puede leer.** Los privilegios por defecto de la primera migración le darían `SELECT` sobre la vista materializada al rol de la aplicación, y ahí no hay política que filtre, así que la migración se lo revoca: un `SELECT` directo sobre `mv_daily_sales` con `sodaya_app` falla con `42501`. La aplicación consulta `v_daily_sales`, una vista normal del dueño del esquema que lee la materializada y filtra con la misma expresión que las políticas, `soda_id = NULLIF(current_setting('app.soda_id', true), '')::uuid`. No es `security_invoker`, porque entonces correría con el rol de la aplicación, que no puede leer la materializada; es `security_barrier = true`, que obliga al planificador a aplicar el filtro de la vista antes que cualquier condición de quien consulta, para que una función con fugas en el `WHERE` no vea filas de otra soda. El adaptador filtra además por `soda_id`, como todo lector. Con la soda B fijada no se ve ninguna fila de la A, y sin soda no se ve ninguna.

Para revisar el resumen a mano:

```sql
-- Con sodaya_owner: todas las sodas.
SELECT * FROM mv_daily_sales ORDER BY soda_id, day;
SELECT * FROM materialized_view_refreshes;

-- Con sodaya_app: solo la soda de la transacción; el SELECT directo falla con 42501.
BEGIN;
SELECT set_config('app.soda_id', '0192f0c4-0000-7000-8000-000000000001', true);
SELECT day, orders, total FROM v_daily_sales ORDER BY day;
EXPLAIN SELECT day, orders, total FROM v_daily_sales WHERE day BETWEEN '2026-10-01' AND '2026-10-31';
SELECT * FROM mv_daily_sales;
ROLLBACK;

-- Con sodaya_maintenance: el refresco.
REFRESH MATERIALIZED VIEW CONCURRENTLY mv_daily_sales;
```

## Extensiones y tipos

| Objeto                 | Migración                | Para qué                                                                                                         |
| ---------------------- | ------------------------ | ---------------------------------------------------------------------------------------------------------------- |
| Extensión `btree_gist` | `create_schedules_table` | Permite comparar con `=` columnas escalares (`uuid`, `smallint`) dentro de un índice GiST                        |
| Tipo `timerange`       | `create_schedules_table` | Rango de `time`, creado con `CREATE TYPE timerange AS RANGE (subtype = time)`; PostgreSQL no lo trae incorporado |

`btree_gist` es una extensión de confianza: el rol dueño de la base la instala sin ser superusuario. Viene en el paquete `contrib` de PostgreSQL.

## Restricciones de exclusión

Una restricción `UNIQUE` solo compara por igualdad. Cuando la regla es «no se traslapan», se usa `EXCLUDE`, que acepta un operador por columna:

```sql
ALTER TABLE schedules ADD CONSTRAINT schedules_no_overlap_excl EXCLUDE USING gist (
    soda_id WITH =,
    day_of_week WITH =,
    timerange(opens_at, closes_at) WITH &&
);
```

Dos filas chocan si tienen la misma soda, el mismo día y rangos con algún minuto en común (`&&`). El rango se construye con los límites por defecto `[)`: la apertura está incluida y el cierre excluido. La violación devuelve el SQLSTATE `23P01`, que el repositorio traduce a la excepción del dominio.

PostgreSQL 18 agrega `WITHOUT OVERLAPS` para llaves primarias y únicas, pero exige que la última columna de la llave sea un rango o multirango. Aquí la franja se guarda como día y dos columnas `time`, así que la herramienta correcta es `EXCLUDE` sobre el rango calculado.

`php artisan migrate:fresh` borra las tablas pero no los tipos. Por eso la migración elimina `timerange` antes de crearlo.

La tabla `promotions` repite el patrón con `promotions_no_overlap_excl` sobre `(soda_id, weekday, timerange(starts_at, ends_at))`: dos horas felices de la misma soda y día no comparten un minuto. El traslape no se valida en PHP, porque dos solicitudes simultáneas pasarían la misma validación; la base lo garantiza y el repositorio traduce el `23P01` a `promocion-traslapada`.

## Seguridad a nivel de fila

Es la segunda barrera del aislamiento entre sodas. La primera está en el código: cada repositorio filtra por `soda_id`. Si una consulta olvida ese filtro, PostgreSQL igual devuelve solo las filas de la soda en curso.

### Qué protege

`categories`, `dishes`, `schedules`, `closures`, `orders`, `order_lines`, `order_status_history` y `pickup_counters` tienen la seguridad a nivel de fila habilitada y forzada, con una política por tabla:

```sql
ALTER TABLE dishes ENABLE ROW LEVEL SECURITY;
ALTER TABLE dishes FORCE ROW LEVEL SECURITY;
CREATE POLICY dishes_soda_isolation ON dishes
    USING (soda_id = NULLIF(current_setting('app.soda_id', true), '')::uuid)
    WITH CHECK (soda_id = NULLIF(current_setting('app.soda_id', true), '')::uuid);
```

| Parte                                  | Qué hace                                                                                                                |
| -------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| `ENABLE ROW LEVEL SECURITY`            | Aplica las políticas a todo rol que no sea el dueño de la tabla; sin una política que lo permita, no se ve ninguna fila |
| `FORCE ROW LEVEL SECURITY`             | Las aplica también al dueño, `sodaya_owner`                                                                             |
| `USING`                                | Decide qué filas existentes se pueden leer, actualizar o borrar                                                         |
| `WITH CHECK`                           | Valida cada fila insertada o modificada: no se puede crear ni mover una fila a otra soda                                |
| `current_setting('app.soda_id', true)` | Lee la soda de la transacción; con `true` devuelve `NULL` en lugar de fallar si la variable no existe                   |
| `NULLIF(..., '')`                      | Convierte el texto vacío en `NULL`; `soda_id = NULL` nunca es verdadero, así que sin soda no se ve ninguna fila         |

Una escritura que no cumple `WITH CHECK` falla con el SQLSTATE `42501` y el mensaje `new row violates row-level security policy`. Un `UPDATE` o un `DELETE` sin soda no falla: no encuentra filas.

### Cómo se fija la soda

La soda vive en la variable `app.soda_id` y se fija con `set_config('app.soda_id', ?, true)`. El tercer argumento la limita a la transacción en curso: al confirmar o deshacer, desaparece, y una conexión reutilizada nunca hereda la soda de la petición anterior. Después de su primera transacción, la variable de esa conexión queda como texto vacío y no como `NULL`; por eso la política usa `NULLIF`.

`SodaTransaction`, en `Src\Shared\Infrastructure\Tenancy`, abre una transacción y fija la soda antes de cualquier consulta. El middleware `soda` la usa en cada petición que toca estas tablas:

| Rutas                                       | Middleware                   | Soda de la transacción       |
| ------------------------------------------- | ---------------------------- | ---------------------------- |
| Personal, `/api/v1/cocina`                  | `auth:sanctum` y `soda:user` | La de la persona autenticada |
| Menú público, `/api/v1/sodas/{soda}/platos` | `soda:route`                 | La de la URL                 |

Una persona sin soda fija el valor vacío y no alcanza ninguna fila. Como el dueño también está sujeto a las políticas, los sembradores escriben los datos de la soda de demostración dentro de `SodaTransaction`, y en las pruebas cada fábrica fija la soda de su fila antes de insertarla. El comando `sodaya:register-soda` solo escribe en `sodas` y `users`, que no tienen política.

### Los pedidos del cliente

Un cliente no tiene soda y sus rutas no la llevan en la URL, pero debe leer sus pedidos en cualquier soda. Para eso `orders`, `order_lines` y `order_status_history` tienen una segunda política, `<tabla>_customer_access`, que solo cubre `SELECT`:

```sql
CREATE POLICY orders_customer_access ON orders FOR SELECT
    USING (customer_id = NULLIF(current_setting('app.customer_id', true), '')::uuid);

CREATE POLICY order_lines_customer_access ON order_lines FOR SELECT
    USING (EXISTS (
        SELECT 1 FROM orders
        WHERE orders.id = order_lines.order_id
          AND orders.customer_id = NULLIF(current_setting('app.customer_id', true), '')::uuid
    ));
```

PostgreSQL combina las políticas permisivas de una tabla con `OR`: con la soda fijada, el personal ve las filas de su soda; con el cliente fijado, el cliente ve las de sus pedidos; sin ninguna de las dos, nada. La política de aislamiento por soda no cambia, y como la del cliente solo cubre `SELECT`, un `INSERT`, un `UPDATE` o un `DELETE` siguen exigiendo la soda: el cliente lee sin soda, pero no escribe sin ella.

El cliente se fija con `set_config('app.customer_id', ?, true)`, local a la transacción como la soda, desde `SodaTransaction::scopeCustomer()`. El middleware `soda` tiene dos fuentes para él:

| Rutas | Middleware | Soda de la transacción | Cliente |
| --- | --- | --- | --- |
| Mis pedidos, `GET /api/v1/pedidos` y `/api/v1/pedidos/{pedido}` | `soda:customer` | Ninguna | El autenticado |
| Acciones sobre un pedido, `/api/v1/pedidos/{pedido}/...` | `soda:order` | La del pedido de la URL, leída como ese cliente | El autenticado |

Con `soda:order`, la búsqueda del pedido pasa por la política del cliente: un pedido ajeno o inexistente no se ve y responde 404 `pedido-no-encontrado`. Si se ve, su soda queda fijada en la misma transacción y la cancelación o el pago tocan `dishes`, el historial y los pagos con las dos barreras de siempre. `CustomerOrderAccessTest` comprueba la visibilidad y que el cliente no escribe; `RowLevelSecurityTest` exige que solo estas tres tablas tengan una segunda política. La decisión está en el [ADR 0008](adr/0008-customer-access-to-orders-through-a-read-policy.md).

### Excepciones

| Tabla   | Por qué no tiene política                                                                                                         |
| ------- | --------------------------------------------------------------------------------------------------------------------------------- |
| `users` | Tiene `soda_id`, pero el ingreso busca a la persona por su correo antes de saber a qué soda pertenece, y un cliente no tiene soda |
| `sodas` | No tiene `soda_id`: cada fila es una soda, y el menú público la busca por el identificador de la URL                              |
| `mv_daily_sales` | Una vista materializada no admite políticas. El rol de la aplicación no la puede leer: consulta `v_daily_sales`, que filtra por la soda de la transacción detrás de una barrera de seguridad («Resumen diario de ventas») |

### Llaves únicas y foráneas

PostgreSQL revisa las llaves únicas, las restricciones de exclusión y las llaves foráneas sin aplicar las políticas. Por eso todas incluyen `soda_id`. Una llave única sin la soda rechazaría un nombre que ya usa otra soda y revelaría que existe; una llave foránea sin la soda permitiría que un plato apunte a la categoría de otra soda.

### Cómo agregar una tabla de una soda

1. La tabla lleva `soda_id`, y sus llaves únicas y foráneas lo incluyen.
2. Una migración habilita y fuerza la seguridad a nivel de fila y crea la política `<tabla>_soda_isolation` con la misma expresión en `USING` y `WITH CHECK`; su `down()` deshace los tres pasos.
3. Las rutas que la usan pasan por el middleware `soda`, y su fábrica usa el trait `CreatesRowsOfASoda`.
4. `RowLevelSecurityTest` revisa el catálogo de PostgreSQL y falla si una tabla con `soda_id`, salvo `users`, no tiene la seguridad habilitada, forzada y con su política. También revisa cada vista con `soda_id`: debe ser `security_invoker`, salvo las que leen una vista materializada, que deben ser `security_barrier` y filtrar por sí mismas.

## Caché en PostgreSQL

La caché de la aplicación (`CACHE_STORE=database`) vive en las tablas `cache` y `cache_locks`, que crea la migración `create_cache_table` del esqueleto de Laravel. No tienen `soda_id` ni política de seguridad a nivel de fila: la soda forma parte de la llave (`menu:{soda}:v{n}`), porque la caché no es la base y la RLS no la protege. `php artisan cache:clear` la vacía; una llave vencida se borra cuando alguien la lee. Las pruebas usan el mismo almacén que producción, para que una prueba no pase gracias a un objeto en memoria que la base no sabría reconstruir.

## Reparto de responsabilidades

El dominio, en PHP, decide las reglas del negocio. PostgreSQL garantiza la integridad de los datos, el aislamiento entre sodas y las consultas por conjuntos. Ningún caso de uso se duplica en PL/pgSQL.

Todo objeto de la base se usa desde `Infrastructure/Persistence`, detrás de un puerto. El dominio y los casos de uso no saben que existen.
