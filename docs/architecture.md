# Arquitectura de sodaya-api-hexagonal

`sodaya-api-hexagonal` combina arquitectura hexagonal (puertos y adaptadores) con diseño guiado por el dominio. El objetivo es que las reglas del negocio se puedan leer, probar y cambiar sin depender de Laravel.

## Estructura

Todo el código del producto vive en `sodaya-api-hexagonal/src/`, bajo el namespace `Src\`. La forma es siempre contexto, módulo y capa:

```
src/
├── Shared/                      Núcleo compartido entre contextos
│   ├── Domain/
│   └── Infrastructure/
└── <Contexto>/                  Catalog (platos, categorías, menú), Sodas (horario, cierres), Identity (cuentas), Ordering (pedidos), Reporting (reportes), Loyalty (puntos) y Billing (pagos)
    ├── Shared/                  Lo común a los módulos del contexto
    └── <Módulo>/                Un agregado; por ejemplo, Dishes
        ├── Domain/
        │   ├── Entities/
        │   ├── ValueObjects/
        │   ├── Contracts/       Puertos (interfaces)
        │   └── Exceptions/
        ├── Application/
        │   ├── UseCases/
        │   └── DTOs/
        └── Infrastructure/
            ├── Http/            Controllers, Requests, Resources y routes.php
            └── Persistence/     Models, Repositories y Queries
```

Un módulo corresponde a un agregado, no a una tabla. Un módulo de solo lectura, como el menú público, no tiene capa `Domain`: su puerto vive en `Application/Contracts`.

Las carpetas `app/`, `bootstrap/`, `config/`, `database/`, `lang/` y `routes/` conservan su función en Laravel. `app/` solo contiene el arranque del framework. Las migraciones, las fábricas y los sembradores se quedan en `database/`.

## Regla de dependencias

Las dependencias apuntan siempre hacia adentro:

```
Infrastructure  ──▶  Application  ──▶  Domain
```

| Capa | Puede depender de | No puede depender de |
| --- | --- | --- |
| Domain | PHP y `Shared\Domain` | Application, Infrastructure, Laravel |
| Application | Domain | Infrastructure, Laravel |
| Infrastructure | Application, Domain, Laravel | — |

La prueba `tests/Architecture/LayerDependencyTest.php` revisa cada archivo de `Domain` y `Application` y falla si alguno rompe la regla.

## Qué va en cada capa

| Capa | Contenido |
| --- | --- |
| Domain | Entidades y agregados, objetos de valor, excepciones del dominio e interfaces de los puertos (por ejemplo, un repositorio). |
| Application | Un caso de uso por clase, con un único método público. Recibe los puertos por constructor y no conoce HTTP ni Eloquent. Los DTO llevan datos primitivos. |
| Infrastructure | Controladores, Form Requests, API Resources, rutas, modelos Eloquent, implementaciones de los puertos y el service provider del módulo. |

Convenciones de nombres: las interfaces, los objetos de valor, los DTO y los casos de uso no llevan sufijo de tipo (`DishRepository`, `Price`, `CreateDish`). Las excepciones terminan en `Exception` y los modelos Eloquent en `Model`. Un agregado se crea con `create()` y se rearma desde la base con `reconstitute()`; su constructor es privado.

## Flujo de una petición

1. La ruta del módulo (`Infrastructure/Http/routes.php`) entrega la petición a un controlador.
2. El middleware `soda` abre una transacción y fija en PostgreSQL la soda de la petición: la de la persona autenticada, la de la URL o la del pedido; en las rutas del cliente fija también al cliente.
3. Un Form Request valida la forma de los datos antes de llegar al controlador.
4. El controlador invoca un caso de uso con un DTO de datos primitivos.
5. El caso de uso trabaja con el dominio y con los puertos.
6. El adaptador de persistencia traduce entre el dominio y Eloquent.
7. Un API Resource construye la respuesta y expone solo los campos permitidos.

## Puertos y adaptadores

Un puerto es una interfaz definida en `Domain/Contracts`. Su adaptador vive en `Infrastructure` y se enlaza en el service provider del módulo, que se registra en `bootstrap/providers.php`. Cambiar de proveedor afecta a un solo adaptador.

El dominio de un módulo solo puede depender de su propio módulo, del `Shared` de su contexto y de `Src\Shared`. Cuando dos contextos necesitan colaborar lo hacen por un puerto o por un evento de dominio, nunca importando el dominio del otro.

### Colaboración entre contextos

El menú público (`Catalog/Menu`) muestra si la soda está abierta, pero la regla pertenece al contexto `Sodas`. El menú no importa ese dominio: declara el puerto `SodaOpenStatus` en su propia capa `Application/Contracts` y lo implementa con un adaptador de infraestructura, `OpeningHoursSodaOpenStatus`, que invoca el caso de uso `CheckSodaIsOpen` de `Sodas/OpeningHours`.

```
PublicMenuController ──▶ GetPublicMenu ──▶ SodaOpenStatus (puerto de Catalog/Menu)
                                                 ▲
                         OpeningHoursSodaOpenStatus (adaptador) ──▶ CheckSodaIsOpen (Sodas/OpeningHours)
```

Si mañana el horario viviera en otro servicio, solo cambiaría el adaptador.

### El tiempo es un parámetro

El dominio nunca pregunta la hora. El momento actual se crea en el borde HTTP con `now()`, en la zona horaria de la aplicación, y viaja como `DateTimeImmutable` hasta el caso de uso y el dominio (`WeeklySchedule::isOpenAt()`, `Closure::create()`). Así las reglas que dependen del reloj se prueban con momentos fijos, sin arrancar el framework, y el indicador `abierta` se calcula en cada respuesta: no se guarda ni se pone en caché.

### El menú público desde caché

El menú se lee miles de veces al día desde celulares y solo cambia cuando el dueño edita. Por eso se sirve desde caché, con la regla de la pizarra: se cachea lo que se lee mucho, cambia poco y se sabe cuándo cambia; lo que cambia con cada venta se pregunta en la caja.

La caché es un adaptador más. `CachedPublicMenuReader`, en `Catalog/Menu/Infrastructure/Cache`, implementa el mismo puerto `PublicMenuReader` que el lector Eloquent y lo envuelve (patrón Decorator): el caso de uso `GetPublicMenu` no cambió ni una línea, y quitar la caché es quitar una línea del `MenuServiceProvider`.

```
GetPublicMenu ──▶ PublicMenuReader (puerto)
                      ▲
          CachedPublicMenuReader ──▶ EloquentPublicMenuReader ──▶ PostgreSQL
                      │
                      └──▶ Cache (tabla cache)
```

| Decisión | Cómo | Por qué |
| --- | --- | --- |
| Llave | `menu:{soda}:v{versión}`; la versión vive en `menu:{soda}:version` | La soda aísla: una llave sin la soda serviría el menú de una a otra, y la RLS no lo impide porque la caché no es la base. La versión invalida sin borrar llaves una por una |
| Qué se guarda | Arreglos planos, con `toArray()` y `fromArray()` en los DTO del menú; nunca modelos ni DTO | `config/cache.php` declara `serializable_classes` en `false`: un objeto guardado en la caché `database` no se reconstruye. La caché `array` de las pruebas lo esconde, por eso las pruebas usan el mismo almacén que producción |
| Qué no se guarda | Las porciones disponibles, el indicador `abierta` y el precio vigente | Cambian con cada pedido, con la hora y con la promoción de la hora. El decorador completa las porciones con una consulta liviana (`id`, `available_portions`) y el caso de uso calcula `abierta` en cada respuesta |
| Invalidación | `MenuCacheInvalidator`, un observador de Eloquent que sube la versión con `Cache::increment` al guardar o borrar un plato, una categoría, una franja, un cierre o la soda (su logo) | Las llaves viejas dejan de leerse y vencen solas; no hacen falta tags, que el almacén `database` no soporta. Los repositorios borran a través del modelo para que el observador lo vea |
| TTL | `MENU_CACHE_SECONDS`, 600 s por defecto | Red de seguridad: si una invalidación se pierde (un cambio por SQL directo), el dato viejo dura diez minutos, no para siempre |
| Estampida | `Cache::flexible([540, 600])` | En el último minuto se sirve el valor viejo y se recalcula en segundo plano, después de responder: una sola consulta, nadie espera |
| Almacén | `database`, la tabla `cache` de PostgreSQL, en pruebas, desarrollo y producción | Misma configuración en todos lados; Redis llega en la clase 23 con solo cambiar `CACHE_STORE` |

Del lado HTTP, las rutas del menú responden `Cache-Control: public, max-age=60` y un `ETag`: el navegador reutiliza el menú un minuto sin llamar a la API y después lo revalida con `If-None-Match`, que responde 304 sin cuerpo si no cambió. El servidor sigue calculando `abierta` y las porciones en cada petición; el navegador puede atrasarlas, como mucho, ese minuto.

`MenuCacheTest` comprueba las cuatro cosas que una caché mal hecha rompe: la segunda lectura no consulta `dishes`, editar invalida, las porciones siguen vivas aunque el menú esté en caché y dos sodas no comparten llave.

### Cuentas y autenticación

El contexto `Identity` guarda las cuentas en el módulo `Identity/Users`. El agregado `User` es PHP puro: sabe que el personal pertenece a una soda y que el cliente no, pero no conoce Eloquent ni Sanctum. Dos puertos lo separan del framework: `UserRepository`, con su adaptador Eloquent, y `PasswordHasher`, cuyo adaptador `BcryptPasswordHasher` usa la fachada `Hash` de Laravel con bcrypt.

El modelo `UserModel` vive en la infraestructura del módulo, extiende el usuario autenticable de Laravel y usa `HasApiTokens` de Sanctum. `config/auth.php` lo declara como proveedor del guard `sanctum`, que es el guard por defecto de la aplicación.

Las operaciones del personal preguntan su soda al puerto `SodaContext` de `Shared`. Su adaptador, `AuthenticatedSodaContext`, la lee de la persona autenticada: la cocina y el dueño trabajan siempre sobre su propia soda y un cliente, que no tiene soda, recibe 403 `prohibido`. Ningún controlador toma la soda de la URL, de los parámetros ni del cuerpo. Cuando este adaptador reemplazó al que leía la soda de la configuración, ningún caso de uso cambió.

### Aislamiento entre sodas

Toda operación del personal se limita a la soda que entrega `SodaContext`. La regla tiene cuatro partes:

1. Cada método de un repositorio recibe el `SodaId` y filtra por `soda_id` en la consulta: buscar, listar y borrar.
2. Un identificador de otra soda en la URL responde 404 `no-encontrado` con el mismo cuerpo que un identificador inexistente. La respuesta no revela que el recurso existe en otra soda.
3. Un identificador de otra soda en el cuerpo, como `categoria_id`, responde 422 con el mismo error que uno inexistente, porque las reglas `exists` y `unique` de los Form Requests también filtran por la soda.
4. Ninguna escritura toca filas de otra soda, y los datos de otra soda no impiden nada: dos sodas pueden tener el mismo nombre de plato, la misma franja o el mismo cierre.

La suite `tests/Feature/Tenancy/StaffIsolationTest.php` lo comprueba con dos sodas, un caso por cada endpoint del personal. Un endpoint nuevo del personal agrega su caso a esa suite.

PostgreSQL es la segunda barrera. Cada petición del personal o del menú público corre en una transacción que fija su soda, y las políticas de seguridad a nivel de fila ocultan las filas de las demás sodas aunque una consulta olvide el filtro. Los repositorios conservan su filtro por `soda_id`: la política no lo reemplaza. El detalle está en [database.md](database.md).

### El cliente y sus pedidos

Un cliente no pertenece a ninguna soda y sus rutas no la llevan en la URL, pero tiene pedidos en varias sodas y debe ver solo los suyos. Las dos barreras se repiten con otro dueño: el repositorio filtra por `customer_id` en `findOfCustomer()` y `pageOfCustomer()`, y la base tiene una política de lectura por cliente en las tablas del pedido, que se activa con `app.customer_id` en la transacción ([database.md](database.md), «Los pedidos del cliente»).

El middleware `soda` tiene cuatro fuentes, una por cada forma de saber sobre qué filas trabaja la petición:

| Fuente | Rutas | Qué fija en la transacción |
| --- | --- | --- |
| `soda:user` | Personal, `/api/v1/cocina` | La soda de la persona autenticada |
| `soda:route` | Menú público y pedir, `/api/v1/sodas/{soda}/...` | La soda de la URL |
| `soda:customer` | Mis pedidos, `GET /api/v1/pedidos` y `/api/v1/pedidos/{pedido}` | Ninguna soda y el cliente autenticado |
| `soda:order` | Acciones sobre un pedido, `/api/v1/pedidos/{pedido}/...` | El cliente autenticado y la soda del pedido de la URL |

`soda:order` busca el pedido como el cliente, por el puerto `SodaOfOrder`, que se declara junto al middleware en `Shared` y que implementa el módulo de pedidos con `EloquentSodaOfOrder`: el núcleo compartido no conoce la tabla `orders`. Si el pedido es visible, su soda queda fijada en la misma transacción y en el atributo `soda_id` de la petición, de donde el controlador la toma para el comando; la cancelación devuelve porciones a `dishes`, el trigger escribe el historial y el pago encontrará su tabla, todo con la soda del pedido. Un pedido ajeno o inexistente no es visible y responde 404 `pedido-no-encontrado`, con el mismo cuerpo en los dos casos: la respuesta no revela que el pedido existe.

`POST /api/v1/pedidos/{pedido}/cancelacion` es la primera acción sobre un pedido. Corre bajo `soda:order` y llama al mismo caso de uso `CancelOrder` que usa la cocina, con el origen `customer`: el controlador toma la soda del atributo que dejó el middleware y no decide nada más. Los efectos son los de toda cancelación: las porciones vuelven a `dishes`, el trigger deja el historial y, si el pedido estaba pagado, se solicita el reembolso; pero a quien cancela no se le avisa de lo que acaba de hacer. Un pedido listo o entregado responde 409 `transicion-no-permitida`, uno ajeno 404 `pedido-no-encontrado`, y cancelar uno ya cancelado no lo cambia.

`GET /api/v1/pedidos` lista los pedidos del cliente del más reciente al más antiguo, paginados como cualquier listado, y `GET /api/v1/pedidos/{pedido}` responde uno. Los dos usan `CustomerOrderResource`, que reutiliza `OrderResource` y agrega `acciones`: los enlaces de lo que el cliente puede hacer con el pedido en su estado, `pagar` mientras está pendiente y sin pago aprobado y `cancelar` mientras está pendiente o confirmado; en un estado final no hay acciones. El cliente que sigue los enlaces no necesita conocer las reglas de estado: si el enlace está, la acción se puede intentar. La decisión completa está en el [ADR 0008](adr/0008-customer-access-to-orders-through-a-read-policy.md).

### Alta de una soda con su dueño

El caso de uso `RegisterSoda`, del módulo `Sodas/Profile`, crea la soda y la cuenta de su dueño en una sola transacción. Colabora con otras piezas por dos puertos: `TransactionRunner`, en `Shared/Domain/Contracts`, con el adaptador `DatabaseTransactionRunner`; y `OwnerAccounts`, en `Application/Contracts` del módulo, cuyo adaptador `IdentityOwnerAccounts` ejecuta el caso de uso `RegisterStaffMember` de Identity. El dominio y la aplicación de Sodas no importan clases de Identity: solo las importa ese adaptador. Si cualquiera de los dos pasos falla, la transacción se revierte y no queda ni la soda ni la cuenta.

### El pedido y sus estados

El contexto `Ordering` guarda los pedidos en el módulo `Ordering/Orders`. El agregado `Order` reúne sus líneas (`OrderLine`), que congelan el nombre y el precio del plato al pedirlo, y es el único que decide un cambio de estado. `OrderStatus` es un enum con su tabla de transiciones: `advanceTo()` mueve el pedido un paso en el flujo de la cocina y `cancel()` lo cancela con su origen. Cada cambio guarda su fecha y hora.

```
pendiente ──▶ confirmado ──▶ listo ──▶ entregado
    │              │
    └──────────────┴──▶ cancelado
```

Un salto, un retroceso o cualquier cambio desde `entregado` o `cancelado` lanza `TransitionNotAllowedException`. Cancelar un pedido ya cancelado no tiene efecto, para que un reintento no repita nada. El dominio no conoce Laravel ni la base de datos: sus reglas se prueban con pruebas unitarias.

Toda cancelación, venga del cliente, de la cocina o de un proceso automático, pasa por el caso de uso `CancelOrder`. En una sola transacción bloquea el pedido con `SELECT ... FOR UPDATE`, lo cancela y devuelve las porciones de sus líneas por el puerto `PortionStock`, cuyo adaptador `DishPortionStock` suma las porciones a cada plato en el orden de sus identificadores. Si algo falla, no se cancela el pedido ni se devuelve nada; si el pedido ya estaba cancelado, no se devuelve nada otra vez.

Un pedido nuevo entra por el caso de uso `PlaceOrder`, desde `POST /api/v1/sodas/{soda}/pedidos`. El comando trae solo el plato y la cantidad de cada línea: el nombre y el precio salen del catálogo de la soda en ese momento, por el puerto `OrderableDishes`, cuyo adaptador `EloquentOrderableDishes` lee los platos de la soda en una consulta. Un plato que la soda no tiene responde 422 `plato-no-pertenece-a-la-soda`, igual si existe en otra soda, y uno desactivado, 409 `plato-no-disponible`. Antes de mirar el catálogo, el caso de uso pregunta al puerto `SodaSchedule` si la soda atiende en ese momento; su adaptador `OpeningHoursSodaSchedule` invoca `CheckSodaIsOpen` del contexto `Sodas`, como hace el menú público, y una soda cerrada responde 409 `soda-cerrada`.

Con las líneas armadas, en una sola transacción crea el agregado, que valida sus líneas, pide al puerto `PortionStock` que reserve las porciones y guarda el pedido. Si `reserve()` responde `false`, lanza `PortionsSoldOutException` y la transacción se deshace: no queda ni el pedido ni la reserva.

El dinero del pedido lo decide el servidor. La línea congela el nombre y el precio que el catálogo tenía al pedir, el subtotal es precio por cantidad y el total es la suma de las líneas: ninguno de los dos se guarda, los calcula el agregado en cada lectura. El Form Request solo toma `plato_id` y `cantidad` de cada línea, así que un `precio_unitario`, un `subtotal` o un `total` enviados por el navegador se ignoran, y `FrozenPriceTest` lo comprueba junto con que un cambio posterior del precio no altera los pedidos ya hechos.

Un doble toque en «confirmar» no hace dos pedidos. Cada solicitud trae el encabezado `Idempotency-Key`, una clave que el cliente elige para ese pedido; sin ella la API responde 400 `solicitud-invalida`, porque no podría distinguir un pedido nuevo de un reintento. El agregado guarda la clave como el objeto de valor `IdempotencyKey`, junto con una huella de sus líneas: platos y cantidades, ordenados, en SHA-256. Antes de tocar el catálogo, `PlaceOrder` busca por el puerto `OrderRepository` el pedido que el cliente ya hizo con esa clave: si existe y la huella coincide, lo devuelve tal cual, con `PlacedOrder::created` en `false` y sin reservar porciones, y la API responde 200; si la huella difiere, lanza `IdempotencyKeyReusedException`, que la API publica como 409 `conflicto`. Dos solicitudes idénticas en el mismo instante pueden pasar las dos esa búsqueda; la llave única `(customer_id, idempotency_key)` de la tabla rechaza la segunda, la transacción se deshace con sus porciones, y el caso de uso vuelve a buscar y responde el pedido que ganó. La clave es única por cliente, no por soda: un cliente puede reutilizar una clave que usó otro.

Las reglas del pedido que el cliente puede romper tienen su propio tipo de problema: un pedido sin líneas responde 422 `pedido-vacio` y una cantidad fuera de 1 a 20, 422 `cantidad-invalida`. El Form Request solo valida la forma de los datos, que `lineas` sea una lista de platos con cantidad; los rangos los decide el dominio, en el mismo lugar que los decide para cualquier otro llamador.

### Número de retiro y hora estimada

El mostrador no entrega un pedido leyendo un UUID: lo llama por su **número de retiro**, el objeto de valor `PickupNumber`, de 1 a 9999, consecutivo por soda y por día de Costa Rica. El agregado no lo inventa: `Order::place()` lo recibe, y es `PlaceOrder` quien lo pide al puerto `PickupNumbers` dentro de la misma transacción que reserva las porciones y guarda el pedido. Si la reserva falla, la transacción se deshace y el número vuelve al contador: dos pedidos de un mismo día nunca comparten número y la numeración no tiene huecos por pedidos que no se crearon.

El adaptador `CounterPickupNumbers` avanza una fila de `pickup_counters` por soda y día con una sola instrucción, `INSERT ... ON CONFLICT DO UPDATE ... RETURNING`: la primera vez crea la fila en 1 y las siguientes suman 1. El bloqueo de fila del `UPDATE` ordena dos pedidos del mismo instante; el segundo espera al primero y recibe el número siguiente. El día se decide en la zona de Costa Rica antes de tocar la base, porque a las 23:30 de Costa Rica ya es mañana en UTC. La llave única `(soda_id, pickup_date, pickup_number)` de `orders` es la última barrera.

La **hora estimada de retiro** la calcula el agregado: `estimatedPickupAt()` es el momento en que se hizo el pedido más el mayor tiempo de preparación de sus líneas. Para eso cada `OrderLine` congela también los `preparationMinutes` que el plato tenía al pedir, de 1 a 120, igual que congela el nombre y el precio: si la soda cambia después los minutos de un plato, la estimación de los pedidos ya hechos no se mueve. Ni el número ni la hora los envía el navegador; el recurso los expone como `numero_retiro` y `retiro_estimado`, ISO 8601 en la zona de Costa Rica, al cliente y a la cocina.

Cuando la cancelación ya quedó confirmada en la base y el pedido estaba pagado, `CancelOrder` solicita el reembolso por el puerto `RefundRequests`. Se solicita después de la transacción para no reembolsar una cancelación que terminó deshaciéndose. Si la cancelación no la hizo el cliente, también le avisa por el puerto `CustomerNotices`. Los dos adaptadores despachan un trabajo a la cola; la sección «Trabajos en cola» los describe.

### La lista de la cocina

`GET /api/v1/cocina/pedidos` lista los pedidos de la soda del personal autenticado, con la ability `cocina`, que tienen la cocina y el dueño. `estado` filtra por `pendiente`, `confirmado`, `listo`, `entregado` o `cancelado`; sin él se listan todos. Cada pedido se responde con `OrderResource`: su número de retiro, su hora estimada de retiro, sus líneas y su total.

El orden es siempre del más antiguo al más reciente, porque el orden en que llegaron los pedidos es el orden en que se preparan: con el filtro por estado, que es como trabaja la cocina, la consulta `WHERE soda_id = ? AND status = ? ORDER BY created_at` sale del índice `(soda_id, status, created_at)` ya ordenada, sin un paso de ordenamiento, y `OrderIndexesTest` lo comprueba con `EXPLAIN ANALYZE` sobre la consulta que el repositorio ejecuta de verdad. Sin filtro, PostgreSQL ordena los pedidos de la soda; es una lectura para revisar, no para preparar. No hay un orden distinto para los estados finales: una sola regla es más fácil de entender y de probar, y para ver lo último entregado basta la última página.

`ListKitchenOrdersRequest` extiende `ListRequest`, la base de todo listado, y le agrega la regla de `estado`: el Form Request valida que el valor sea uno de los cinco de la API y lo traduce al vocabulario del dominio; el caso de uso `ListKitchenOrders` recibe el estado ya traducido, o ninguno, y pide la página al repositorio con `pageOfSoda()`. Las líneas de la página se cargan en una sola consulta, y `ListingBudgetTest` exige que el listado corra las mismas consultas con 1 pedido que con 50.

### Plazos del pedido

Un pedido nuevo tiene 10 minutos para que se apruebe su pago. El agregado conoce la regla: `paymentDeadline()` es el momento en que se hizo más 10 minutos, y `expire($now)` lo cancela con el origen `payment_timeout` si en ese momento sigue pendiente y sin pago. Si no corresponde, responde `false` y no cambia nada.

Con el pago aprobado empieza otro plazo: la cocina tiene 10 minutos para confirmar el pedido. `confirmationDeadline()` es el momento del pago más 10 minutos, y `expire($now)` cancela con el origen `confirmation_timeout` un pedido pagado que en ese momento sigue pendiente. Como el pedido estaba pagado, `CancellationEffects` pide el reembolso.

Quien decide cuándo mirar es `PlaceOrder`, para el plazo de pago, y `ApproveOrderPayment`, para el de confirmación. Este último caso de uso registra el pago aprobado; lo llamará el módulo de pagos de la clase 13 cuando la pasarela apruebe el cobro. Los dos casos de uso, después de guardar el pedido, le piden al puerto `OrderDeadlines` que lo revise en su plazo. El adaptador `QueuedOrderDeadlines` despacha `ExpireOrderJob` con `delay()`: el trabajo entra a la tabla `jobs` de inmediato, pero su `available_at` es el plazo, y ningún trabajador lo toma antes. Cuando corre, abre una transacción con la soda del pedido, porque un trabajador no tiene una petición de la que sacarla, y llama al caso de uso `ExpireOrder`.

La cola puede perder un trabajo: un trabajador que se cae a mitad de camino, una tabla `jobs` restaurada de un respaldo. Como red de seguridad, el comando `sodaya:expire-orders` corre cada minuto. Pide al puerto `ExpiredOrders` los pedidos pendientes cuyo plazo de pago o de confirmación ya pasó, en todas las sodas, y a cada uno le aplica `ExpireOrder` dentro de una transacción de su soda. El adaptador `MaintenanceExpiredOrders` lee con el rol de mantenimiento, porque recorre todas las sodas, pero no cancela con él: la cancelación pasa por el caso de uso, con el rol de la aplicación y la soda fijada, como cualquier otra. Si el trabajo de la cola y el comando llegan al mismo pedido, el segundo encuentra el pedido ya cancelado y no hace nada.

`CancelOrder` y `ExpireOrder` comparten `CancellationEffects`: dentro de la transacción, guardar el pedido y devolver sus porciones; después de confirmar, el reembolso y el aviso al cliente. Así toda cancelación, venga de quien venga, tiene los mismos efectos.

### Trabajos en cola

Lo que tarda o depende de un servicio externo no se hace dentro de la petición: se despacha a la cola y un proceso aparte, `php artisan queue:work`, lo ejecuta. La cola es la tabla `jobs` de PostgreSQL hasta que llegue Redis.

El caso de uso no sabe que existe una cola. Llama a un puerto, como `RefundRequests` o `CustomerNotices`, y el adaptador de infraestructura despacha el trabajo: `QueuedRefundRequests` despacha `RequestRefundJob` y `QueuedCustomerNotices` despacha `NotifyCustomerJob`. Cambiar la cola por una llamada directa, o al revés, es cambiar el adaptador.

| Trabajo | Lo despacha | Llega en |
| --- | --- | --- |
| `NotifyCustomerJob`, avisos al cliente | `CustomerNotices` al marcar listo o al cancelar un pedido | Clase 10; el canal, en la clase 11 |
| `RequestRefundJob`, reembolsos | `RefundRequests` al cancelar un pedido pagado | Clase 10 |
| `ExpireOrderJob`, plazos del pedido | `OrderDeadlines` al hacer un pedido | Clase 10 |
| `AwardPurchasePointsJob`, acreditación de puntos | `LoyaltyRewards` al entregar un pedido | Clase 10 |
| Lecturas de la pizarra, resúmenes y eventos de calendario | Sus módulos | Clases siguientes |

Reglas de todo trabajo, que `tests/Architecture/QueuedJobsTest.php` revisa en cada clase de `Infrastructure/Jobs`:

- Implementa `ShouldQueue` y declara `$tries` mayor que 1 y un `$backoff`. Una falla transitoria, como un servicio que no responde, se reintenta esperando cada vez más: 10 segundos, un minuto y cinco minutos.
- El trabajo que agota sus intentos queda en `failed_jobs`, con su contenido y la excepción. `php artisan queue:failed` los lista y `php artisan queue:retry` los vuelve a encolar.
- La conexión `database` tiene `after_commit` activo: un trabajo despachado dentro de una transacción solo entra a la cola si la transacción se confirma. Un reembolso nunca sale por una cancelación que terminó deshaciéndose.
- Recibe identificadores y valores simples, nunca modelos de Eloquent ni agregados: el trabajo se guarda como texto y puede ejecutarse minutos después, cuando el pedido ya cambió.

El reembolso deja todavía su resultado en el log: el cobro y el reembolso reales llegan con el módulo de pagos, y cuando lleguen cambia el `handle()` del trabajo y nada más. El aviso al cliente ya sale de verdad, por el canal que elige la configuración; la sección siguiente lo describe.

### Avisos al cliente por canales intercambiables

El cliente recibe un aviso cuando su pedido queda listo y cuando se cancela. Quién avisa y cuándo es cosa de la aplicación: `AdvanceOrder` llama a `CustomerNotices::orderReady()` después de confirmar el paso a listo, y `CancellationEffects` a `orderCancelled()` después de confirmar una cancelación que no hizo el propio cliente. El adaptador `QueuedCustomerNotices` deja en la cola `NotifyCustomerJob` con tres valores simples: el pedido, su soda y lo que pasó, `order_ready` u `order_cancelled`. Nada más viaja en el trabajo: el nombre, el correo, el número de retiro, el origen de la cancelación y el reembolso se leen cuando el trabajo corre, porque para entonces pueden haber cambiado.

Por dónde sale el aviso es cosa de la infraestructura, y es el patrón **Strategy**: el puerto `NoticeChannel`, en `Application/Contracts`, tiene un solo método, `send(Recipient $to, string $subject, string $message)`, y dos estrategias en `Infrastructure/Notices`: `LogNoticeChannel` escribe el aviso en el log y `MailNoticeChannel` lo envía con `Mail` como el `Mailable` `CustomerNoticeMail`, sin plantilla, al correo del cliente. `OrdersServiceProvider` es el único lugar que sabe cuáles canales existen: un `match` sobre `config('sodaya.notice_channel')`, que viene de `NOTICE_CHANNEL`, `log` por defecto, entrega una u otra clase; un valor desconocido lanza `UnhandledMatchError` en el primer aviso, en vez de no avisarle a nadie en silencio. Agregar WhatsApp es agregar una clase y una línea en el `match`.

```
AdvanceOrder ──▶ CustomerNotices ──▶ QueuedCustomerNotices ──▶ cola ──▶ NotifyCustomerJob::handle(NoticeChannel)
                                                                                  │
                                                           ┌──────────────────────┴───────────────┐
                                                    LogNoticeChannel                      MailNoticeChannel
```

El trabajo no decide el canal: lo pide en `handle()` y el contenedor del trabajador se lo entrega con la configuración del trabajador, así que cambiar `NOTICE_CHANNEL` cambia el canal de todos los avisos sin tocar ningún trabajo ni caso de uso. Para leer el pedido abre una transacción de su soda, como `ExpireOrderJob`, y para saber a quién escribirle pregunta al puerto `Recipients`, cuyo adaptador `IdentityRecipients` lee el correo y el nombre de la cuenta del cliente en Identity. Los textos están en `lang/es/notices.php`: el asunto nombra el número de retiro, y el aviso de cancelación dice quién canceló y si hay reembolso, con el monto.

Tres pruebas, tres razones para fallar: `NoticeChannelTest` comprueba que la configuración elige la clase y que cada canal hace su trabajo; `CustomerNoticesTest`, que marcar listo deja el trabajo en la cola con el pedido correcto, después de confirmar, y que ejecutarlo con el canal de correo manda el correo al cliente del pedido con el texto esperado. Que el correo llegue de verdad es cosa del proveedor de correo, no de SodaYa.

### Tareas programadas

Un módulo que necesita una tarea periódica registra su comando de consola y lo programa en el `boot()` de su proveedor, con `callAfterResolving(Schedule::class, ...)`. Así cada módulo trae su programación como trae sus rutas, y `php artisan schedule:list` las muestra todas.

| Tarea | Comando | Cuándo | Rol |
| --- | --- | --- | --- |
| Restablecer las porciones | `sodaya:reset-daily-portions` | Cada día a las 5:00, hora de Costa Rica (`DAILY_PORTIONS_RESET_AT`) | `sodaya_maintenance` |
| Cancelar los pedidos vencidos | `sodaya:expire-orders` | Cada minuto | `sodaya_maintenance` para buscarlos; `sodaya_app` para cancelarlos |
| Recalcular el resumen diario de ventas | `sodaya:refresh-daily-sales` | Cada 15 minutos | `sodaya_maintenance` |

Toda tarea lleva `withoutOverlapping()`: si una ejecución todavía no termina cuando llega la siguiente, la nueva no arranca. La hora se escribe con `timezone('America/Costa_Rica')` aunque la aplicación ya use esa zona, para que un cambio de `APP_TIMEZONE` no mueva la tarea. En producción, el programador corre con una entrada de cron que ejecuta `php artisan schedule:run` cada minuto; en desarrollo, con `php artisan schedule:work`.

### Puntos de lealtad

El contexto `Loyalty` guarda los puntos en el módulo `Loyalty/Points` como un libro de movimientos. `PointMovement` es una línea del libro: se crea con `purchase()` o `redemption()` y no tiene métodos que la cambien. El repositorio solo agrega movimientos, suma el saldo y lee los últimos; no tiene `save()` ni `delete()`. `add()` responde `false` cuando el pedido ya acreditó sus puntos, gracias al índice único parcial de la tabla.

Un pedido entregado acredita 1 punto por cada ₡1000 completos del total de sus líneas; la propina no cuenta, porque el pedido todavía no la tiene y, cuando la tenga, no es una compra en la soda. `AdvanceOrder` avisa por el puerto `LoyaltyRewards` del contexto de pedidos, y su adaptador `QueuedLoyaltyRewards` despacha `AwardPurchasePointsJob`, del contexto de puntos, con el cliente, el pedido y el monto. El trabajo llama al caso de uso `AwardPurchasePoints`, que calcula los puntos con `Points::earnedWith()` y los agrega al libro. Si el trabajo corre dos veces, el índice único parcial hace que la segunda no agregue nada.

Un canje pasa por `RedeemPoints`: bloquea el libro del cliente, lee el saldo, y si alcanza agrega el movimiento negativo; si no, lanza `InsufficientPointsException`, que la API publica como 409 `puntos-insuficientes`. No hay una fila de saldo que bloquear, porque el saldo no se guarda. El repositorio toma un bloqueo consultivo de PostgreSQL con la clave del cliente, `pg_advisory_xact_lock(hashtextextended('point_movements:<cliente>', 0))`, que dura hasta el final de la transacción: un segundo canje del mismo cliente espera, y cuando lee el saldo ya ve el primer canje. Los canjes de clientes distintos no se esperan entre sí. El valor del punto y su uso como descuento en un pedido están pendientes de definir con la clienta (P-02).

El cliente consulta su saldo y sus últimos 20 movimientos con `GetPointsStatement`. Las rutas de `/api/v1/puntos` piden la ability `pedidos`, que solo tiene el cliente, y toman su identidad del token: no hay un parámetro que permita pedir los puntos de otra persona.

`Loyalty` tiene sus propios `CustomerId` y `OrderId`: el dominio de un módulo no depende del de otro, aunque los dos identifiquen las mismas filas.

### Reportes

El contexto `Reporting` solo lee. Su módulo `Reporting/Sales` no tiene dominio propio: el caso de uso `GetHourlySales` pide los datos al puerto de lectura `SalesReport` y devuelve un DTO. El adaptador `PostgresSalesReport` llama a la función `fn_hourly_sales` de la base, que hace la agrupación. No pasa por los modelos de Eloquent de `Ordering`: un reporte agrega miles de filas y conviene que lo haga PostgreSQL, y así el contexto de reportes no depende de las clases de otro contexto.

El reporte de ventas por día, `GET /api/v1/cocina/reportes/ventas-diarias?desde=AAAA-MM-DD&hasta=AAAA-MM-DD`, compara meses, así que no se calcula en cada consulta: `GetDailySales` pide al mismo puerto los días del rango y el momento del último cálculo, y el adaptador los lee de `v_daily_sales`, la vista sobre el resumen precalculado `mv_daily_sales`, y de `materialized_view_refreshes`. La respuesta trae `dias`, con `fecha`, `pedidos` y `total` de cada día que tuvo entregas, y `actualizado_en`, el momento del último refresco en ISO 8601 o `null` si todavía no hubo ninguno: el dueño sabe de cuándo son las cifras. Un día cuenta sus pedidos por la fecha de Costa Rica en que se crearon, la misma regla que el reporte por hora y que el número de retiro, así que las horas de un día suman lo que ese día muestra en el reporte diario. El Form Request valida solo la forma del rango: dos fechas, la final no anterior a la inicial y 366 días como máximo.

El resumen lo mantiene el otro puerto del módulo, `DailySalesSummary`, con un solo método, `refresh($now)`. El caso de uso `RefreshDailySales` lo llama desde el comando `sodaya:refresh-daily-sales`, y el adaptador `MaintenanceDailySalesSummary` refresca la vista materializada con el rol de mantenimiento, sin bloquear las lecturas, y guarda el momento en la misma transacción. El comando corre cada 15 minutos: el día en curso queda a lo sumo un cuarto de hora atrás, al costo de una consulta de agregación cada vez; un reporte que compara meses no necesita más. El seeder `DailySalesSeeder` ejecuta el mismo caso de uso al final de `php artisan db:seed`, para que el reporte muestre los pedidos de demostración sin esperar al programador. Los detalles de la vista materializada, de por qué no aplica la seguridad a nivel de fila y de cómo se aísla cada soda están en «Resumen diario de ventas» de [database.md](database.md).

### Pagos

El contexto `Billing` reúne lo que mueve dinero: hoy el cobro de un pedido; en la clase 17, el reembolso y la comisión de la plataforma. No es facturación electrónica, que queda fuera del alcance. Su módulo `Billing/Payments` no importa el dominio de `Ordering`: tiene sus propios `OrderId` y `CustomerId`, como `Loyalty`, y colabora con los pedidos por puertos.

El proveedor de pagos vive detrás del puerto `PaymentGateway`, en `Application/Contracts`, con tres operaciones: `provider()`, el nombre con que se guarda cada pago; `charge(ChargeRequest)`, que cobra una vez; y `status(ChargeReference)`, que pregunta qué pasó con un cobro. El núcleo habla en sus términos. `ChargeRequest` lleva la clave de idempotencia, el monto en colones enteros (`Amount`), el método de pago del cliente y el pedido. `ChargeResult` trae el estado que toma el pago (`PaymentStatus`: aprobado, rechazado o pendiente), la referencia que el proveedor le dio al cobro (`ChargeReference`) y, si el banco lo rechazó, su código. Son objetos de valor del dominio, no DTO de la aplicación, porque el agregado `Payment` los recibe para cerrar el pago.

El adaptador `StripeGateway`, en `Infrastructure/Stripe`, es la capa anticorrupción: traduce entre esos objetos y la forma de la API de Stripe, sin SDK, con el cliente HTTP de Laravel. Manda `POST /v1/payment_intents` con `confirm=true`, el monto en céntimos (`colones * 100`, porque CRC no está en la lista de monedas sin decimales de Stripe), `currency=crc`, la llave secreta como bearer, el cuerpo como formulario y el encabezado `Idempotency-Key`; y `GET /v1/payment_intents/{id}` para consultar. Lee tres desenlaces: 200 con `status: succeeded` es aprobado; 402 con `card_declined` es rechazado, con el `decline_code` del banco y el intento que Stripe alcanzó a crear; y un 200 con otro estado, como `processing`, es pendiente. Lo demás no es una decisión: un timeout o una conexión caída se reintenta tres veces con la misma clave, con 200 ms entre intentos, y después lanza `GatewayUnavailableException`; un 5xx, un 401 o cualquier otra respuesta fallida la lanza sin reintentar. Un 402 no se reintenta nunca, porque sería volver a pedirle permiso al banco con la misma tarjeta. Cada llamada espera a lo sumo 10 segundos. El `retry()` del cliente HTTP se configura con `throw: false`: con el valor por defecto, un 402 saldría del cliente como `RequestException` y la API respondería 500 en lugar de un desenlace de negocio.

`GatewayUnavailableException` extiende `ServiceUnavailableException`, de `Shared`, y `ProblemDetailsRenderer` la publica como 503 `servicio-no-disponible` con el encabezado `Retry-After: 30`. Cualquier otro servicio externo que se caiga puede reutilizar esa base.

El agregado `Payment` es un intento de pagar un pedido. Nace con `open()`: pendiente, sin referencia, con el proveedor, el monto que vale el pedido y una clave de idempotencia derivada de su propia identidad, `pago-{uuid}`. `settle(ChargeResult, $now)` registra lo que respondió el proveedor: aprobado o rechazado lo cierran, con la referencia o con el código del banco y el momento; un proveedor que todavía no decide solo deja su referencia; y un pago cerrado nunca cambia, así que una consulta que corre dos veces no cambia nada. Solo un pago rechazado deja lugar a otro intento: pendiente y aprobado son el **pago vivo** del pedido.

`PayOrder` cobra en tres pasos. Primero, una transacción corta: por el puerto `PayableOrders` lee el pedido del cliente, con la suma de sus líneas congeladas y si está pendiente y pagado; un pedido ajeno o inexistente responde 404, uno pagado 409 `pedido-ya-pagado` y uno que no está pendiente 409 `pedido-no-pagable`; después abre el intento con `PaymentRepository::open()`, que inserta la fila o, si el pedido ya tiene un pago vivo, devuelve ese. Segundo, fuera de toda transacción, pide el cobro al `PaymentGateway` con la clave del intento: la llamada puede tardar diez segundos y un cobro no se deshace con rollback, así que ninguna fila queda bloqueada mientras tanto. Tercero, otra transacción: `settle()` cierra el pago, el repositorio lo guarda y, si quedó aprobado, el puerto `OrderPayments` marca el pedido pagado; su adaptador `OrderingOrderPayments` ejecuta `ApproveOrderPayment` del contexto de pedidos, que registra el pago y programa el plazo de confirmación. El pago y el pedido cambian juntos o ninguno.

El monto nunca viene del navegador: `PayOrderCommand` lleva el pedido, el cliente y el método de pago, y nada más; el adaptador `EloquentPayableOrders` suma `unit_price × quantity` de las líneas guardadas. Los tres desenlaces: aprobado, el pedido queda pagado y la API responde 201 con el pago; rechazado, el pago se guarda con el código del banco y después se lanza `PaymentRejectedException`, que la API publica como 402 `pago-rechazado`, y el cliente puede volver a intentar mientras el pedido siga pendiente, porque el pago rechazado no cuenta como vivo; error de comunicación, el intento queda pendiente, con la referencia si alcanzó a llegar, y `GatewayUnavailableException` sale como 503 con `Retry-After: 30`.

Oprimir «pagar» dos veces no cobra dos veces, por dos barreras. El índice único parcial `payments_live_order_unique`, sobre `order_id` solo en las filas pendientes o aprobadas, hace que la segunda solicitud reciba el mismo pago: el repositorio inserta dentro de un savepoint y, cuando el índice rechaza la fila, responde el pago que ya existe; si dos solicitudes llegan en el mismo instante, solo una gana el índice y la otra recibe el pago de la ganadora. Y la clave `pago-{uuid}` viaja en el encabezado `Idempotency-Key`: si el pago vivo sigue pendiente, la segunda solicitud vuelve a pedir el cobro con la misma clave y Stripe repite la respuesta original en lugar de crear otro cobro. La prueba `PayOrderTest` cubre los tres desenlaces, el doble clic, el monto ignorado y las dos transacciones con dobles en memoria; `PaymentsSchemaTest` y `EloquentPaymentRepositoryTest`, el índice parcial, las restricciones y la política de la tabla.

`ConfirmPayment` responde el estado real del último pago de un pedido del cliente, para `GET /api/v1/pedidos/{pedido}/pago`. Si el proveedor ya decidió, responde lo guardado sin preguntar. Si el pago está pendiente y tiene referencia, pregunta con `status()` al `PaymentGateway`, fuera de toda transacción, y después, en una sola transacción, bloquea el pago con `findForUpdate()`, lo cierra con `settle()`, lo guarda y, si quedó aprobado, marca el pedido pagado por `OrderPayments`. Corrido dos veces, la segunda encuentra el pago cerrado y no pregunta ni escribe nada. Un pago pendiente sin referencia nunca llegó al proveedor: no hay a quién preguntar, y el cliente puede volver a pagar, con la misma clave, mientras el pedido siga pendiente. `ConfirmPaymentCommand` lleva el pedido y el cliente, nada más: ningún request acepta el estado del pago, porque el estado lo decide el proveedor, hoy consultado y desde la clase 17 firmado en un webhook (RF-PAG-07). Un pedido sin pagos responde 404.

Las rutas del módulo, `POST` y `GET /api/v1/pedidos/{pedido}/pago`, piden `auth:sanctum` y la ability `pedidos`, y pasan por el middleware `soda:order` de los pedidos del cliente, que fija en la transacción la soda del pedido de la URL y responde 404 `pedido-no-encontrado` ante un pedido ajeno o inexistente; así `OrderPaymentController` nunca ve un pedido que no sea del cliente autenticado.

La llave vive en `STRIPE_SECRET` y se lee en `config/services.php`. El adaptador rechaza al construirse cualquier llave que no empiece por `sk_test_`: solo se aceptan credenciales de prueba, y `PaymentsServiceProvider` es el único lugar que nombra a Stripe. Ninguna prueba llama a Stripe: `tests/TestCase.php` activa `Http::preventStrayRequests()`, así cualquier llamada no simulada falla la prueba, y las pruebas del adaptador usan `Http::fake`, `Http::assertSent` para revisar el número exacto que viaja (₡5600 son 560000) y `Http::failedConnection` para el proveedor caído. El doble `FakePaymentGateway`, en `tests/Support/Billing`, responde lo que la prueba le indique, sin red.

### Imágenes

Las fotos de los platos y el logo de la soda pasan por el puerto `ImageStorage` de `Src\Shared\Domain\Contracts`. Su adaptador, `FilesystemImageStorage`, guarda cada archivo en el disco que indica `MEDIA_DISK` con un nombre UUID v7 que genera el servidor: el nombre que envía el navegador nunca se usa. El Form Request acepta JPG, PNG y WebP de hasta 2 MB y 4000 × 4000 píxeles, y decide el tipo por el contenido del archivo, no por su extensión. La base guarda solo la ruta; el menú y los recursos la convierten en la dirección pública con el mismo puerto.

## Errores

El dominio lanza excepciones que extienden `Src\Shared\Domain\Exceptions\DomainException`. Cada excepción lleva una clave de traducción, no un texto: el mensaje en español se resuelve en la infraestructura desde `lang/es`.

Toda respuesta de error sigue RFC 9457 (`application/problem+json`). `ProblemDetailsRenderer` convierte cada excepción en un documento con `type`, `title`, `status`, `detail` e `instance`:

| Excepción | Tipo de problema | HTTP |
| --- | --- | --- |
| Validación de un Form Request | `datos-invalidos`, con `errores` por campo | 422 |
| `InvalidValueException` | `datos-invalidos` | 422 |
| `NotFoundException` | `no-encontrado`, o el tipo que la excepción nombra con `type()`: `pedido-no-encontrado` para un pedido ajeno o inexistente | 404 |
| `AuthenticationFailedException`, como un correo o una contraseña incorrectos | `credenciales-invalidas` | 401 |
| Ruta protegida sin un token válido (`AuthenticationException` del framework) | `no-autenticado` | 401 |
| Persona autenticada sin permiso: un token sin la ability que pide la ruta (`MissingAbilityException` de Sanctum) o un cliente en una ruta del personal. Ambas son `AuthorizationException` del framework | `prohibido` | 403 |
| `BusinessRuleException`, una regla de negocio con nombre propio: `transicion-no-permitida` si el pedido no puede pasar al estado pedido, `pedido-sin-pago` si se confirma sin pago aprobado, `porciones-agotadas` si un plato no tiene las porciones que el pedido pide, `puntos-insuficientes` si un canje supera el saldo, `pedido-ya-pagado` si se paga un pedido con pago aprobado y `pedido-no-pagable` si el pedido ya no está pendiente | El tipo que nombra la regla; `conflicto` si el catálogo no lo tiene | 409 |
| `PaymentRejectedException`, también una `BusinessRuleException`: el banco no autorizó la tarjeta | `pago-rechazado`, con el código del banco en `detail` | 402 |
| Otra excepción del dominio, como una franja traslapada o un cierre repetido | `conflicto` | 409 |
| `ServiceUnavailableException`, un servicio externo que no responde: `GatewayUnavailableException` si el proveedor de pagos no contesta o falla | `servicio-no-disponible`, con el encabezado `Retry-After: 30` | 503 |
| Excepción HTTP del framework | El tipo de su código de estado | 4xx o 5xx |
| Cualquier otra | `error-interno`, sin detalles técnicos | 500 |

Laravel convierte toda `AuthorizationException` en una excepción HTTP 403 antes de llamar a `ProblemDetailsRenderer`, que la traduce al tipo de su código de estado. Por eso la excepción de Sanctum no necesita una rama propia en el renderizador.

El campo `instance` repite el identificador de la solicitud, que también viaja en el encabezado `X-Request-Id` y en cada línea de log.

## Listados

Todo listado del personal, y el de los pedidos del cliente, va paginado con `pagina` y `por_pagina`: 15 elementos por defecto y 50 como máximo. La respuesta trae los elementos en `data` y la página en `meta`: `pagina`, `por_pagina`, `total` y `ultima_pagina`. El menú público es la excepción: el visitante recibe todos los platos activos de una vez.

| Pieza | Capa | Qué hace |
| --- | --- | --- |
| `PageRequest` y `Page` | `Shared/Domain` | La página pedida y la página leída, con el total del listado |
| `pageOf()` o `from()` en cada repositorio | `Domain/Contracts` | Reciben un `PageRequest` y devuelven un `Page` |
| `EloquentPages` | `Shared/Infrastructure` | Cuenta las filas y lee solo las de la página: dos consultas, sin importar el tamaño |
| `ListRequest` y `PageMeta` | `Shared/Infrastructure/Http` | Validan los parámetros y escriben `meta` |

Ningún listado consulta la base por cada elemento. Fuera de producción, `Model::shouldBeStrict()` hace fallar la carga diferida de una relación, y una prueba compara las consultas de un listado con 1 fila y con 50. Otra prueba llena una página de 50 elementos con todos sus textos al máximo y exige que pese 100 KB o menos. Esa prueba mostró que el JSON escapaba cada tilde como `\u00e1`, seis bytes en lugar de dos: el middleware `EncodeJsonAsUnicode` hace que toda respuesta JSON viaje en UTF-8 y sin escapar las barras.

## Contrato de la API

Scramble genera el contrato OpenAPI 3.1 desde el código. La documentación interactiva está en `/docs/api` y el contrato se versiona en `sodaya-api-hexagonal/openapi/v1.json`. Las operaciones detrás del middleware `auth:sanctum` exigen el esquema `bearer` y documentan la respuesta 401 `no-autenticado`; las demás se marcan como públicas. Tras cambiar un endpoint hay que regenerarlo con `composer openapi`: una prueba falla si el archivo versionado difiere del código.

## Rutas

`routes/api.php` incluye el archivo de rutas de cada módulo. Todas se sirven bajo `/api/v1`, prefijo que se configura una sola vez en `bootstrap/app.php`. Un cambio incompatible se publica como `/api/v2`.

Las rutas del personal se agrupan bajo `/api/v1/cocina` y pasan por el middleware `auth:sanctum`. El menú público y el ingreso no lo usan.

## Pruebas

| Carpeta | Alcance |
| --- | --- |
| `tests/Unit` | Dominio y casos de uso, sin arrancar el framework. |
| `tests/Feature` | Endpoints y adaptadores contra PostgreSQL, con el rol de la aplicación. `tests/Feature/Tenancy` reúne las pruebas de aislamiento entre sodas. |
| `tests/Architecture` | Regla de dependencias entre capas y entre módulos. |

## Convenciones

Las convenciones de idioma y de nombres están en [ubiquitous-language.md](ubiquitous-language.md).

## Base de datos y decisiones

Los roles, las migraciones y las convenciones del esquema están en [database.md](database.md); el modelo de datos, en [data-model.md](data-model.md). El motivo de cada decisión de arquitectura está en [adr/](adr/) y los patrones usados, en [patterns.md](patterns.md).
