# Modelo de datos

El esquema está en tercera forma normal. Este documento describe las tablas que ya existen con todo su detalle y las entidades previstas a nivel de relación; cada módulo completa las suyas en el pull request que agrega su migración.

Las convenciones de nombres, llaves, fechas y dinero están en [database.md](database.md). Las tablas `categories`, `dishes`, `schedules`, `closures`, `orders`, `order_lines` y `order_status_history` tienen seguridad a nivel de fila forzada: cada transacción solo alcanza las filas de su soda. El detalle y las excepciones están en la sección «Seguridad a nivel de fila» de ese documento.

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
    orders ||--o| payments : "se paga con"
    users ||--o{ point_movements : "acumula"

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
    }
    order_lines {
        uuid id PK
        uuid order_id FK
        uuid soda_id FK
        uuid dish_id FK
        varchar dish_name
        integer unit_price
        smallint quantity
    }    order_status_history {
        uuid id PK
        uuid order_id FK
        uuid soda_id FK
        varchar from_status
        varchar to_status
        timestamptz changed_at
    }
```

Las tablas sin columnas en el diagrama están previstas y todavía no existen.

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
| `created_at`, `updated_at` | `timestamptz` | `created_at` es el momento en que se hizo el pedido |

Índices: `customer_id` y `(soda_id, status, created_at)`, que sirve a la lista de cocina y al reporte de ventas.

Llaves candidatas: `id` y `(id, soda_id)`; la segunda existe para que las líneas referencien el pedido junto con su soda. `orders_cancellation_check` exige que un pedido cancelado tenga origen y momento de cancelación, y que ningún otro los tenga. `orders_paid_at_check` exige que `paid_at` exista exactamente cuando el pago está aprobado.

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

Un plato aparece una sola vez en cada pedido: `(order_id, dish_id)` es única. Las dos llaves foráneas compuestas impiden una línea con el pedido o el plato de otra soda, aunque el identificador exista. Para eso `dishes` gana la llave única `(id, soda_id)`.

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
| La línea del pedido guarda el nombre y el precio del plato | No es un dato derivado: es el hecho histórico de a cuánto se vendió. Cambiar el precio del plato no debe alterar pedidos pasados |
| `personal_access_tokens.id` es `bigint` y no `uuid` | La tabla pertenece a Sanctum: el token que recibe el cliente tiene la forma `<id>\|<secreto>` y Sanctum busca la fila por ese número. No es un identificador del negocio |
| `personal_access_tokens` no tiene llave foránea a `users` | Sanctum define una relación polimórfica (`tokenable_type`, `tokenable_id`). Un token cuya cuenta ya no existe no autentica a nadie, porque Sanctum no encuentra a su dueño |

## Regla para cada migración nueva

Antes de fusionar un pull request con una migración se revisa que la tabla tenga llave primaria `id`, que lleve `soda_id` si pertenece a una soda, que sus llaves únicas y foráneas incluyan `soda_id`, que tenga la seguridad a nivel de fila habilitada y forzada con su política `<tabla>_soda_isolation`, que no guarde valores calculables y que este documento quede actualizado.
