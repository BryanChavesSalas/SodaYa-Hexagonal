# Modelo de datos

El esquema está en tercera forma normal. Este documento describe las tablas que ya existen con todo su detalle y las entidades previstas a nivel de relación; cada módulo completa las suyas en el pull request que agrega su migración.

Las convenciones de nombres, llaves, fechas y dinero están en [database.md](database.md). Las tablas `categories`, `dishes`, `schedules`, `closures`, `orders`, `order_lines`, `order_status_history`, `pickup_counters` y `payments` tienen seguridad a nivel de fila forzada: cada transacción solo alcanza las filas de su soda. El detalle y las excepciones están en la sección «Seguridad a nivel de fila» de ese documento.

## Diagrama

```mermaid
erDiagram
    sodas ||--o{ categories : "tiene"
    sodas ||--o{ dishes : "ofrece"
    categories |o--o{ dishes : "agrupa"
    sodas ||--o{ schedules : "atiende en"
    sodas ||--o{ closures : "cierra en"
    sodas |o--o{ users : "emplea"
    users ||--o{ personal_access_tokens : "ingresa con"
    sodas ||--o{ orders : "recibe"
    users ||--o{ orders : "hace"
    users ||--o{ point_movements : "acumula"
    orders ||--o| point_movements : "acredita"
    orders ||--|{ order_lines : "contiene"
    dishes ||--o{ order_lines : "se vende en"
    orders ||--o{ order_status_history : "pasa por"
    sodas ||--o{ pickup_counters : "numera con"
    orders ||--o{ payments : "se intenta pagar con"
    sodas ||--o{ payments : "cobra"
    users ||--o{ payments : "paga"

    sodas {
        uuid id PK
        varchar name
        varchar payment_account_id
    }
    categories {
        uuid id PK
        uuid soda_id FK
        varchar name
    }
    dishes {
        uuid id PK
        uuid soda_id FK
        uuid category_id FK
        varchar name
        integer price
    }
    schedules {
        uuid id PK
        uuid soda_id FK
        smallint day_of_week
        time opens_at
        time closes_at
    }
    closures {
        uuid id PK
        uuid soda_id FK
        date closed_on
        varchar reason
    }
    users {
        uuid id PK
        uuid soda_id FK
        varchar email
        varchar role
    }
    personal_access_tokens {
        bigint id PK
        uuid tokenable_id
        varchar token
    }
    point_movements {
        uuid id PK
        uuid customer_id FK
        uuid order_id FK
        varchar reason
        integer points
    }
    orders {
        uuid id PK
        uuid soda_id FK
        uuid customer_id FK
        varchar status
        varchar cancellation_origin
        varchar payment_status
        date pickup_date
        smallint pickup_number
    }
    order_lines {
        uuid id PK
        uuid order_id FK
        uuid soda_id FK
        uuid dish_id FK
        varchar dish_name
        integer unit_price
        smallint quantity
        smallint preparation_minutes
    }
    pickup_counters {
        uuid soda_id PK, FK
        date pickup_date PK
        smallint last_number
    }
    order_status_history {
        uuid id PK
        uuid order_id FK
        uuid soda_id FK
        varchar from_status
        varchar to_status
        timestamptz changed_at
    }
    payments {
        uuid id PK
        uuid order_id FK
        uuid soda_id FK
        uuid customer_id FK
        varchar provider
        varchar reference
        integer amount
        varchar status
        varchar decline_code
        varchar idempotency_key
    }
```

## Tablas del catálogo

### sodas

| Columna | Tipo | Regla |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria, `DEFAULT uuidv7()` |
| `name` | `varchar(120)` | Obligatoria |
| `payment_account_id` | `varchar(255)` | Opcional; identificador de la cuenta de pago de la soda |
| `logo_path` | `varchar(255)` | Opcional; ruta del logo en el disco de imágenes, con un nombre generado por el servidor |
| `created_at`, `updated_at` | `timestamptz` | |

### categories

| Columna | Tipo | Regla |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria, `DEFAULT uuidv7()` |
| `soda_id` | `uuid` | Llave foránea a `sodas`, borrado en cascada |
| `name` | `varchar(60)` | Única dentro de la soda |
| `created_at`, `updated_at` | `timestamptz` | |

Llaves candidatas: `id`, `(soda_id, name)` y `(id, soda_id)`. La última existe para que `dishes` pueda referenciar la categoría junto con su soda.

### dishes

| Columna | Tipo | Regla |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria, `DEFAULT uuidv7()` |
| `soda_id` | `uuid` | Llave foránea a `sodas`, borrado en cascada |
| `category_id` | `uuid` | Opcional; llave foránea compuesta `(category_id, soda_id)` a `categories` |
| `name` | `varchar(120)` | Única dentro de la soda |
| `description` | `varchar(500)` | Opcional |
| `price` | `integer` | Colones enteros, `CHECK` entre 100 y 100 000 |
| `preparation_minutes` | `smallint` | `CHECK` entre 1 y 120 |
| `available_portions` | `smallint` | `CHECK` entre 0 y 500; las que quedan hoy |
| `base_portions` | `smallint` | `CHECK` entre 0 y 500; las que la soda prepara en un día normal, por defecto 0 |
| `is_active` | `boolean` | Por defecto `true` |
| `photo_path` | `varchar(255)` | Opcional; ruta de la foto en el disco de imágenes, con un nombre generado por el servidor |
| `created_at`, `updated_at` | `timestamptz` | |

Al borrar una categoría, `category_id` de sus platos queda en nulo y el plato se conserva.

`base_portions` llegó con una migración compatible: la columna se agrega con un valor por defecto, así el código anterior sigue insertando platos sin conocerla, y los platos que ya existían toman como base sus porciones del momento. Para ese relleno la migración quita y vuelve a poner `FORCE ROW LEVEL SECURITY` dentro de su transacción, porque el dueño de la tabla también está sujeto a la política y sin soda no vería ninguna fila.

## Tablas de la soda

### schedules

Cada fila es una franja del horario de atención: un día de la semana y un intervalo de horas. Un día puede tener varias franjas.

| Columna | Tipo | Regla |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria, `DEFAULT uuidv7()` |
| `soda_id` | `uuid` | Llave foránea a `sodas`, borrado en cascada |
| `day_of_week` | `smallint` | `CHECK` entre 1 (lunes) y 7 (domingo), según ISO 8601 |
| `opens_at` | `time` | Hora de apertura, incluida en la franja |
| `closes_at` | `time` | Hora de cierre, excluida de la franja; `CHECK (opens_at < closes_at)` |
| `created_at`, `updated_at` | `timestamptz` | |

La restricción de exclusión `schedules_no_overlap_excl` impide que dos franjas de la misma soda y del mismo día se traslapen. Dos franjas que se tocan (`08:00–12:00` y `12:00–15:00`) son válidas, porque el cierre no pertenece a la franja.

Las horas no llevan zona horaria: son horas de pared de la soda, que opera en `America/Costa_Rica`.

### closures

Días en que la soda no abre, aunque su horario diga lo contrario.

| Columna | Tipo | Regla |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria, `DEFAULT uuidv7()` |
| `soda_id` | `uuid` | Llave foránea a `sodas`, borrado en cascada |
| `closed_on` | `date` | Fecha del cierre, en la zona horaria de Costa Rica; única dentro de la soda |
| `reason` | `varchar(200)` | Opcional |
| `created_at`, `updated_at` | `timestamptz` | |

Llaves candidatas: `id` y `(soda_id, closed_on)`. La segunda garantiza un solo cierre por soda y fecha. «Cerrar por el resto del día» no se guarda aparte: es un cierre con la fecha de hoy.

## Tablas de pedidos

### orders

| Columna | Tipo | Regla |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria, `DEFAULT uuidv7()` |
| `soda_id` | `uuid` | Llave foránea a `sodas`, borrado en cascada |
| `customer_id` | `uuid` | Llave foránea a `users`; una cuenta con pedidos no se puede borrar |
| `status` | `varchar(20)` | `pending`, `confirmed`, `ready`, `delivered` o `cancelled`; por defecto `pending` |
| `cancellation_origin` | `varchar(30)` | `customer`, `kitchen`, `payment_timeout` o `confirmation_timeout` |
| `confirmed_at`, `ready_at`, `delivered_at`, `cancelled_at` | `timestamptz` | Momento en que el pedido llegó a cada estado |
| `payment_status` | `varchar(20)` | `pending` o `approved`; por defecto `pending` |
| `paid_at` | `timestamptz` | Momento en que se aprobó el pago; existe solo si el pago está aprobado |
| `idempotency_key` | `varchar(100)` | Clave que el cliente envió con la solicitud; única por cliente |
| `request_fingerprint` | `char(64)` | SHA-256 de las líneas de la solicitud; existe exactamente cuando hay clave |
| `pickup_date` | `date` | Día de Costa Rica en que se hizo el pedido; el día al que pertenece su número |
| `pickup_number` | `smallint` | Número de retiro, `CHECK` entre 1 y 9999; consecutivo dentro de la soda y el día |
| `created_at`, `updated_at` | `timestamptz` | `created_at` es el momento en que se hizo el pedido |

Índices: `customer_id`, `(soda_id, status, created_at)`, que sirve a la lista de cocina y al reporte de ventas, la llave única `(customer_id, idempotency_key)`, que impide que una clave cree dos pedidos, y la llave única `(soda_id, pickup_date, pickup_number)`, que impide que dos pedidos de una soda compartan número el mismo día.

Llaves candidatas: `id`, `(id, soda_id)` y `(soda_id, pickup_date, pickup_number)`; la segunda existe para que las líneas referencien el pedido junto con su soda. `orders_cancellation_check` exige que un pedido cancelado tenga origen y momento de cancelación, y que ningún otro los tenga. `orders_paid_at_check` exige que `paid_at` exista exactamente cuando el pago está aprobado.

`pickup_date` se guarda aunque se derive de `created_at`: la llave única necesita el día de Costa Rica como columna, y una expresión con zona horaria dentro de la llave la volvería ilegible. La hora estimada de retiro no se guarda: se calcula con la creación y los minutos de las líneas.

### order_lines

| Columna | Tipo | Regla |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria, `DEFAULT uuidv7()` |
| `order_id` | `uuid` | Llave foránea compuesta `(order_id, soda_id)` a `orders`, borrado en cascada |
| `soda_id` | `uuid` | Llave foránea a `sodas`, borrado en cascada |
| `dish_id` | `uuid` | Llave foránea compuesta `(dish_id, soda_id)` a `dishes`; un plato pedido no se puede borrar |
| `dish_name` | `varchar(120)` | Nombre del plato al momento de pedir |
| `unit_price` | `integer` | Precio del plato al momento de pedir, `CHECK` entre 100 y 100 000 |
| `quantity` | `smallint` | `CHECK` entre 1 y 20 |
| `preparation_minutes` | `smallint` | Minutos de preparación del plato al momento de pedir, `CHECK` entre 1 y 120 |

Un plato aparece una sola vez en cada pedido: `(order_id, dish_id)` es única. Las dos llaves foráneas compuestas impiden una línea con el pedido o el plato de otra soda, aunque el identificador exista. Para eso `dishes` gana la llave única `(id, soda_id)`.

### pickup_counters

Una fila por soda y día con el último número de retiro entregado. La avanza `CounterPickupNumbers` con `INSERT ... ON CONFLICT DO UPDATE`; la describe la sección «Contador de números de retiro» de [database.md](database.md).

| Columna | Tipo | Regla |
| --- | --- | --- |
| `soda_id` | `uuid` | Llave foránea a `sodas`, borrado en cascada |
| `pickup_date` | `date` | Día de Costa Rica |
| `last_number` | `smallint` | Último número entregado ese día; por defecto 0 |

La llave primaria es `(soda_id, pickup_date)`: no hay `id` porque la fila no tiene identidad propia, es el contador de ese día. No tiene marcas de tiempo porque cambia con cada pedido y su historia es la de los pedidos.

### order_status_history

Una fila por cada cambio de estado de un pedido. La escribe el trigger `trg_orders_status_history`, no la aplicación.

| Columna | Tipo | Regla |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria, `DEFAULT uuidv7()` |
| `order_id` | `uuid` | Llave foránea compuesta `(order_id, soda_id)` a `orders`, borrado en cascada |
| `soda_id` | `uuid` | Llave foránea a `sodas`, borrado en cascada |
| `from_status` | `varchar(20)` | Estado anterior |
| `to_status` | `varchar(20)` | Estado nuevo |
| `changed_at` | `timestamptz` | Hora del cambio, `DEFAULT now()` |

El rol de la aplicación no puede actualizar ni borrar sus filas.

### v_hourly_sales

Vista, no tabla: una fila por pedido entregado. La describe la sección «Ventas por hora» de [database.md](database.md).

| Columna | Origen |
| --- | --- |
| `soda_id`, `order_id`, `created_at` | `orders` |
| `sale_hour` | Hora de Costa Rica de `created_at`, de 0 a 23 |
| `total` | Suma de `unit_price × quantity` de las líneas |

## Tablas de reportes

### mv_daily_sales

Vista materializada: una fila por soda y día de Costa Rica con pedidos entregados, calculada por `fn_daily_sales_of_every_soda()` y refrescada cada 15 minutos. La describe la sección «Resumen diario de ventas» de [database.md](database.md). El rol de la aplicación la consulta a través de `v_daily_sales`, que tiene las mismas columnas y muestra solo la soda de la transacción.

| Columna | Tipo | Origen |
| --- | --- | --- |
| `soda_id` | `uuid` | `orders` |
| `day` | `date` | Fecha de Costa Rica de `created_at` del pedido |
| `orders` | `bigint` | Cantidad de pedidos entregados ese día |
| `total` | `bigint` | Suma de `unit_price × quantity` de las líneas de esos pedidos, sin propinas |

Índice único `mv_daily_sales_soda_id_day_unique` sobre `(soda_id, day)`: lo exige `REFRESH MATERIALIZED VIEW CONCURRENTLY` y sirve a la consulta por rango de fechas.

### materialized_view_refreshes

Una fila por vista materializada con el momento de su último refresco. La escribe el rol de mantenimiento en la misma transacción que el refresco; el rol de la aplicación solo la lee.

| Columna | Tipo | Regla |
| --- | --- | --- |
| `view_name` | `varchar(63)` | Llave primaria; nombre de la vista materializada, como `mv_daily_sales` |
| `refreshed_at` | `timestamptz` | Momento del último refresco |

No tiene `soda_id`: el refresco recalcula todas las sodas a la vez. La llave primaria es el nombre de la vista, un identificador de PostgreSQL de 63 caracteres como máximo, y no un `uuid`: la fila se busca siempre por ese nombre y nunca se referencia desde otra tabla.

## Tablas de puntos

### point_movements

El libro de puntos de cada cliente. Solo se agregan filas: el saldo es la suma de `points` y no se guarda en ninguna columna.

| Columna | Tipo | Regla |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria, `DEFAULT uuidv7()` |
| `customer_id` | `uuid` | Llave foránea a `users`; un cliente con movimientos no se puede borrar |
| `order_id` | `uuid` | Llave foránea a `orders`; obligatoria en una compra, opcional en un canje |
| `reason` | `varchar(20)` | `purchase` o `redemption` |
| `points` | `integer` | Positivo en una compra y negativo en un canje |
| `created_at` | `timestamptz` | Momento del movimiento |

`point_movements_points_check` exige que el signo coincida con el motivo y que una compra tenga pedido. El índice único parcial `point_movements_purchase_order_unique`, sobre `order_id` solo en las filas de compra, deja un pedido acreditar puntos una sola vez sin impedir que un pedido se pague con puntos. La tabla no tiene `soda_id`: los puntos son del cliente en toda la plataforma.

## Tablas de pagos

### payments

Cada fila es un intento de pagar un pedido. Un pedido puede tener varios intentos rechazados, pero a lo sumo uno pendiente o aprobado.

| Columna | Tipo | Regla |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria, `DEFAULT uuidv7()` |
| `order_id` | `uuid` | Llave foránea compuesta `(order_id, soda_id)` a `orders`, borrado en cascada |
| `soda_id` | `uuid` | Llave foránea a `sodas`, borrado en cascada |
| `customer_id` | `uuid` | Llave foránea a `users`; una cuenta con pagos no se puede borrar |
| `provider` | `varchar(20)` | Proveedor que cobra: `stripe` |
| `reference` | `varchar(255)` | Opcional; identificador que el proveedor le dio al cobro, como `pi_…`; única por proveedor |
| `amount` | `integer` | Colones enteros, `CHECK (amount > 0)`; la suma de las líneas del pedido al abrir el intento |
| `status` | `varchar(20)` | `pending`, `approved` o `rejected`; por defecto `pending` |
| `decline_code` | `varchar(60)` | Opcional; código del banco, solo en un pago rechazado |
| `idempotency_key` | `varchar(100)` | Clave enviada al proveedor, `pago-{id}`; única |
| `settled_at` | `timestamptz` | Momento en que el proveedor decidió; existe exactamente cuando el pago no está pendiente |
| `created_at`, `updated_at` | `timestamptz` | `created_at` es el momento en que se abrió el intento |

El índice único parcial `payments_live_order_unique`, sobre `order_id` solo en las filas con `status IN ('pending', 'approved')`, es el que impide dos pagos vivos para un pedido: el repositorio inserta el intento y, si el índice lo rechaza, responde el pago que ya existe. Un pago rechazado no entra en el índice, así que el cliente puede volver a intentar. `payments_settled_at_check` exige el momento en todo pago cerrado y lo prohíbe en uno pendiente; `payments_decline_code_check` solo admite el código del banco en un pago rechazado. El índice `(order_id, created_at)` sirve para leer el último intento de un pedido.

La llave foránea compuesta a `orders` impide que un pago apunte al pedido de otra soda aunque el identificador exista, igual que en `order_lines`.

## Tablas de identidad

### users

Cada fila es una cuenta: un cliente o una persona del personal de una soda.

| Columna | Tipo | Regla |
| --- | --- | --- |
| `id` | `uuid` | Llave primaria, `DEFAULT uuidv7()` |
| `name` | `varchar(120)` | Obligatoria |
| `email` | `varchar(255)` | Única; se guarda recortada y en minúsculas |
| `password` | `varchar(255)` | Hash bcrypt de la contraseña, nunca la contraseña |
| `phone` | `text` | Opcional; se cifrará en la clase 13 |
| `role` | `varchar(20)` | `CHECK` entre `customer`, `kitchen` y `owner` |
| `soda_id` | `uuid` | Opcional; llave foránea a `sodas`, borrado en cascada |
| `is_active` | `boolean` | Por defecto `true`; una cuenta inactiva no puede ingresar |
| `terms_accepted_at` | `timestamptz` | Opcional |
| `whatsapp_consent_at` | `timestamptz` | Opcional |
| `created_at`, `updated_at` | `timestamptz` | |

Llaves candidatas: `id` y `email`. La restricción `users_soda_id_check` exige que el personal (`kitchen`, `owner`) tenga soda y que el cliente no la tenga.

El correo es único en todo el sistema y no dentro de cada soda: la persona ingresa con su correo antes de que se sepa a qué soda pertenece. Por la misma razón, `users` no tiene política de seguridad a nivel de fila aunque lleve `soda_id`.

### personal_access_tokens

La crea Laravel Sanctum. Cada fila es un token de acceso emitido para un dispositivo.

| Columna | Tipo | Regla |
| --- | --- | --- |
| `id` | `bigint` | Llave primaria autoincremental |
| `tokenable_type`, `tokenable_id` | `varchar`, `uuid` | Dueño del token: la clase del modelo y su identificador |
| `name` | `text` | Nombre del dispositivo |
| `token` | `varchar(64)` | Único; hash SHA-256 del token, nunca el token |
| `abilities` | `text` | Lista de abilities en JSON |
| `last_used_at` | `timestamptz` | Opcional; último uso |
| `expires_at` | `timestamptz` | Opcional, con índice |
| `created_at`, `updated_at` | `timestamptz` | |

## Verificación de las formas normales

| Forma | Qué exige | Cómo se cumple |
| --- | --- | --- |
| Primera | Valores atómicos, sin grupos repetidos | Una fila por plato y una columna por dato; la categoría es una tabla, no una lista en el plato |
| Segunda | Ningún dato depende de una parte de la llave | Las llaves primarias son de una sola columna (`id`) |
| Tercera | Ningún dato depende de otro que no sea llave | El nombre de la categoría vive en `categories`, no en `dishes`; «agotado» no se guarda, se calcula de `available_portions` |

## Excepciones deliberadas

| Excepción | Por qué es correcta |
| --- | --- |
| `soda_id` se repite en `dishes` aunque la categoría ya lo implica | El plato pertenece a la soda aunque no tenga categoría. La llave foránea compuesta `(category_id, soda_id)` impide que los dos valores discrepen |
| `soda_id` se repite en `order_lines` aunque el pedido ya lo implica | La seguridad a nivel de fila filtra cada tabla por su propia columna, y las llaves compuestas `(order_id, soda_id)` y `(dish_id, soda_id)` impiden que los valores discrepen |
| El saldo de puntos no se guarda | Se calcula sumando los movimientos; un saldo guardado podría descuadrarse con ellos y nadie sabría cuál vale |
| El total del pedido no se guarda | Se calcula sumando las líneas; guardarlo crearía un dato que puede contradecirlas |
| La línea del pedido guarda el nombre, el precio y los minutos del plato | No son datos derivados: son el hecho histórico de a cuánto se vendió y cuánto tardaba. Cambiar el plato no debe alterar pedidos pasados ni su hora estimada de retiro |
| `orders.pickup_date` repite el día de `created_at` | La llave única por soda, día y número necesita el día de Costa Rica como columna; el repositorio escribe los dos a la vez a partir del mismo momento |
| `pickup_counters` guarda el último número aunque sea el máximo de `orders` | Es la fila que se bloquea para numerar: sin ella, dos pedidos simultáneos leerían el mismo máximo y chocarían con la llave única |
| `mv_daily_sales` guarda totales que salen de las líneas | Es un dato derivado a propósito: una copia precalculada que se recalcula entera cada 15 minutos y nunca se edita, y el reporte dice de cuándo es. Ninguna regla del negocio la lee; solo el reporte |
| `payments.amount` se guarda aunque sea la suma de las líneas del pedido | Es lo que se le pidió cobrar al proveedor en ese momento: si mañana el pedido admitiera propina o descuento, los pagos ya hechos conservan su monto |
| `payments.idempotency_key` se guarda aunque se derive de `id` | Es la clave que viajó al proveedor, un hecho. Si la derivación cambiara, los intentos ya enviados conservan la clave con que el proveedor los reconoce |
| `personal_access_tokens.id` es `bigint` y no `uuid` | La tabla pertenece a Sanctum: el token que recibe el cliente tiene la forma `<id>\|<secreto>` y Sanctum busca la fila por ese número. No es un identificador del negocio |
| `personal_access_tokens` no tiene llave foránea a `users` | Sanctum define una relación polimórfica (`tokenable_type`, `tokenable_id`). Un token cuya cuenta ya no existe no autentica a nadie, porque Sanctum no encuentra a su dueño |

## Regla para cada migración nueva

Antes de fusionar un pull request con una migración se revisa que la tabla tenga llave primaria `id`, que lleve `soda_id` si pertenece a una soda, que sus llaves únicas y foráneas incluyan `soda_id`, que tenga la seguridad a nivel de fila habilitada y forzada con su política `<tabla>_soda_isolation`, que no guarde valores calculables y que este documento quede actualizado.
