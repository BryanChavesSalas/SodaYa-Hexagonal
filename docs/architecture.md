# Arquitectura de sodaya-api-hexagonal

`sodaya-api-hexagonal` combina arquitectura hexagonal (puertos y adaptadores) con diseño guiado por el dominio. El objetivo es que las reglas del negocio se puedan leer, probar y cambiar sin depender de Laravel.

## Estructura

Todo el código del producto vive en `sodaya-api-hexagonal/src/`, bajo el namespace `Src\`. La forma es siempre contexto, módulo y capa:

```
src/
├── Shared/                      Núcleo compartido entre contextos
│   ├── Domain/
│   └── Infrastructure/
└── <Contexto>/                  Catalog (platos, categorías, menú), Sodas (horario, cierres), Identity (cuentas), Ordering (pedidos), Reporting (reportes) y Loyalty (puntos)
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
2. En las rutas del personal y del menú público, el middleware `soda` abre una transacción y fija en PostgreSQL la soda de la petición.
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

Las reglas del pedido que el cliente puede romper tienen su propio tipo de problema: un pedido sin líneas responde 422 `pedido-vacio` y una cantidad fuera de 1 a 20, 422 `cantidad-invalida`. El Form Request solo valida la forma de los datos, que `lineas` sea una lista de platos con cantidad; los rangos los decide el dominio, en el mismo lugar que los decide para cualquier otro llamador.

Cuando la cancelación ya quedó confirmada en la base y el pedido estaba pagado, `CancelOrder` solicita el reembolso por el puerto `RefundRequests`. Se solicita después de la transacción para no reembolsar una cancelación que terminó deshaciéndose. Si la cancelación no la hizo el cliente, también le avisa por el puerto `CustomerNotices`. Los dos adaptadores despachan un trabajo a la cola; la sección «Trabajos en cola» los describe.

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
| `NotifyCustomerJob`, avisos al cliente | `CustomerNotices` al cancelar un pedido | Clase 10 |
| `RequestRefundJob`, reembolsos | `RefundRequests` al cancelar un pedido pagado | Clase 10 |
| `ExpireOrderJob`, plazos del pedido | `OrderDeadlines` al hacer un pedido | Clase 10 |
| `AwardPurchasePointsJob`, acreditación de puntos | `LoyaltyRewards` al entregar un pedido | Clase 10 |
| Lecturas de la pizarra, resúmenes y eventos de calendario | Sus módulos | Clases siguientes |

Reglas de todo trabajo, que `tests/Architecture/QueuedJobsTest.php` revisa en cada clase de `Infrastructure/Jobs`:

- Implementa `ShouldQueue` y declara `$tries` mayor que 1 y un `$backoff`. Una falla transitoria, como un servicio que no responde, se reintenta esperando cada vez más: 10 segundos, un minuto y cinco minutos.
- El trabajo que agota sus intentos queda en `failed_jobs`, con su contenido y la excepción. `php artisan queue:failed` los lista y `php artisan queue:retry` los vuelve a encolar.
- La conexión `database` tiene `after_commit` activo: un trabajo despachado dentro de una transacción solo entra a la cola si la transacción se confirma. Un reembolso nunca sale por una cancelación que terminó deshaciéndose.
- Recibe identificadores y valores simples, nunca modelos de Eloquent ni agregados: el trabajo se guarda como texto y puede ejecutarse minutos después, cuando el pedido ya cambió.

Los trabajos actuales dejan su resultado en el log: el cobro y el reembolso reales llegan con el módulo de pagos, y el correo con el de notificaciones. Cuando lleguen, cambia el `handle()` del trabajo y nada más.

### Tareas programadas

Un módulo que necesita una tarea periódica registra su comando de consola y lo programa en el `boot()` de su proveedor, con `callAfterResolving(Schedule::class, ...)`. Así cada módulo trae su programación como trae sus rutas, y `php artisan schedule:list` las muestra todas.

| Tarea | Comando | Cuándo | Rol |
| --- | --- | --- | --- |
| Restablecer las porciones | `sodaya:reset-daily-portions` | Cada día a las 5:00, hora de Costa Rica (`DAILY_PORTIONS_RESET_AT`) | `sodaya_maintenance` |
| Cancelar los pedidos vencidos | `sodaya:expire-orders` | Cada minuto | `sodaya_maintenance` para buscarlos; `sodaya_app` para cancelarlos |

Toda tarea lleva `withoutOverlapping()`: si una ejecución todavía no termina cuando llega la siguiente, la nueva no arranca. La hora se escribe con `timezone('America/Costa_Rica')` aunque la aplicación ya use esa zona, para que un cambio de `APP_TIMEZONE` no mueva la tarea. En producción, el programador corre con una entrada de cron que ejecuta `php artisan schedule:run` cada minuto; en desarrollo, con `php artisan schedule:work`.

### Puntos de lealtad

El contexto `Loyalty` guarda los puntos en el módulo `Loyalty/Points` como un libro de movimientos. `PointMovement` es una línea del libro: se crea con `purchase()` o `redemption()` y no tiene métodos que la cambien. El repositorio solo agrega movimientos, suma el saldo y lee los últimos; no tiene `save()` ni `delete()`. `add()` responde `false` cuando el pedido ya acreditó sus puntos, gracias al índice único parcial de la tabla.

Un pedido entregado acredita 1 punto por cada ₡1000 completos del total de sus líneas; la propina no cuenta, porque el pedido todavía no la tiene y, cuando la tenga, no es una compra en la soda. `AdvanceOrder` avisa por el puerto `LoyaltyRewards` del contexto de pedidos, y su adaptador `QueuedLoyaltyRewards` despacha `AwardPurchasePointsJob`, del contexto de puntos, con el cliente, el pedido y el monto. El trabajo llama al caso de uso `AwardPurchasePoints`, que calcula los puntos con `Points::earnedWith()` y los agrega al libro. Si el trabajo corre dos veces, el índice único parcial hace que la segunda no agregue nada.

Un canje pasa por `RedeemPoints`: bloquea el libro del cliente, lee el saldo, y si alcanza agrega el movimiento negativo; si no, lanza `InsufficientPointsException`, que la API publica como 409 `puntos-insuficientes`. No hay una fila de saldo que bloquear, porque el saldo no se guarda. El repositorio toma un bloqueo consultivo de PostgreSQL con la clave del cliente, `pg_advisory_xact_lock(hashtextextended('point_movements:<cliente>', 0))`, que dura hasta el final de la transacción: un segundo canje del mismo cliente espera, y cuando lee el saldo ya ve el primer canje. Los canjes de clientes distintos no se esperan entre sí. El valor del punto y su uso como descuento en un pedido están pendientes de definir con la clienta (P-02).

El cliente consulta su saldo y sus últimos 20 movimientos con `GetPointsStatement`. Las rutas de `/api/v1/puntos` piden la ability `pedidos`, que solo tiene el cliente, y toman su identidad del token: no hay un parámetro que permita pedir los puntos de otra persona.

`Loyalty` tiene sus propios `CustomerId` y `OrderId`: el dominio de un módulo no depende del de otro, aunque los dos identifiquen las mismas filas.

### Reportes

El contexto `Reporting` solo lee. Su módulo `Reporting/Sales` no tiene dominio propio: el caso de uso `GetHourlySales` pide los datos al puerto de lectura `SalesReport` y devuelve un DTO. El adaptador `PostgresSalesReport` llama a la función `fn_hourly_sales` de la base, que hace la agrupación. No pasa por los modelos de Eloquent de `Ordering`: un reporte agrega miles de filas y conviene que lo haga PostgreSQL, y así el contexto de reportes no depende de las clases de otro contexto.

### Imágenes

Las fotos de los platos y el logo de la soda pasan por el puerto `ImageStorage` de `Src\Shared\Domain\Contracts`. Su adaptador, `FilesystemImageStorage`, guarda cada archivo en el disco que indica `MEDIA_DISK` con un nombre UUID v7 que genera el servidor: el nombre que envía el navegador nunca se usa. El Form Request acepta JPG, PNG y WebP de hasta 2 MB y 4000 × 4000 píxeles, y decide el tipo por el contenido del archivo, no por su extensión. La base guarda solo la ruta; el menú y los recursos la convierten en la dirección pública con el mismo puerto.

## Errores

El dominio lanza excepciones que extienden `Src\Shared\Domain\Exceptions\DomainException`. Cada excepción lleva una clave de traducción, no un texto: el mensaje en español se resuelve en la infraestructura desde `lang/es`.

Toda respuesta de error sigue RFC 9457 (`application/problem+json`). `ProblemDetailsRenderer` convierte cada excepción en un documento con `type`, `title`, `status`, `detail` e `instance`:

| Excepción | Tipo de problema | HTTP |
| --- | --- | --- |
| Validación de un Form Request | `datos-invalidos`, con `errores` por campo | 422 |
| `InvalidValueException` | `datos-invalidos` | 422 |
| `NotFoundException` | `no-encontrado` | 404 |
| `AuthenticationFailedException`, como un correo o una contraseña incorrectos | `credenciales-invalidas` | 401 |
| Ruta protegida sin un token válido (`AuthenticationException` del framework) | `no-autenticado` | 401 |
| Persona autenticada sin permiso: un token sin la ability que pide la ruta (`MissingAbilityException` de Sanctum) o un cliente en una ruta del personal. Ambas son `AuthorizationException` del framework | `prohibido` | 403 |
| `BusinessRuleException`, una regla de negocio con nombre propio: `transicion-no-permitida` si el pedido no puede pasar al estado pedido, `pedido-sin-pago` si se confirma sin pago aprobado, `porciones-agotadas` si un plato no tiene las porciones que el pedido pide y `puntos-insuficientes` si un canje supera el saldo | El tipo que nombra la regla; `conflicto` si el catálogo no lo tiene | 409 |
| Otra excepción del dominio, como una franja traslapada o un cierre repetido | `conflicto` | 409 |
| Excepción HTTP del framework | El tipo de su código de estado | 4xx o 5xx |
| Cualquier otra | `error-interno`, sin detalles técnicos | 500 |

Laravel convierte toda `AuthorizationException` en una excepción HTTP 403 antes de llamar a `ProblemDetailsRenderer`, que la traduce al tipo de su código de estado. Por eso la excepción de Sanctum no necesita una rama propia en el renderizador.

El campo `instance` repite el identificador de la solicitud, que también viaja en el encabezado `X-Request-Id` y en cada línea de log.

## Listados

Todo listado del personal va paginado con `pagina` y `por_pagina`: 15 elementos por defecto y 50 como máximo. La respuesta trae los elementos en `data` y la página en `meta`: `pagina`, `por_pagina`, `total` y `ultima_pagina`. El menú público es la excepción: el visitante recibe todos los platos activos de una vez.

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
