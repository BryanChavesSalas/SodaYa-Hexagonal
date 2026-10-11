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

El script `database/roles.sql` crea los dos roles y las dos bases. Lo ejecuta una sola vez un superusuario, con las contraseñas como variables de `psql`: nunca se versionan.

La primera migración otorga al rol de la aplicación, con `ALTER DEFAULT PRIVILEGES`, los permisos de lectura y escritura sobre toda tabla y secuencia que el dueño cree después. Ninguna tabla nueva necesita un paso manual.

Por qué importa: en PostgreSQL los superusuarios y los roles con `BYPASSRLS` no pasan por las políticas de seguridad a nivel de fila, y el dueño de una tabla tampoco, salvo que la tabla las fuerce. Las tablas de una soda las fuerzan, pero la aplicación igual se conecta con un rol que no es dueño ni tiene `BYPASSRLS`: el aislamiento no depende de una sola línea de una migración.

Un tercer rol, de mantenimiento, se agrega cuando exista la primera tarea que recorra todas las sodas.

## Migraciones

- Se ejecutan siempre con el rol dueño: `php artisan migrate --database=pgsql_admin`.
- Cada migración tiene su `down()` y una prueba las ejecuta hacia atrás y hacia adelante.
- Un cambio que rompe la versión anterior del código se hace en dos despliegues: primero se expande el esquema y, cuando el código viejo ya no corre, se contrae.

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

| Objeto              | Prefijo                  | Regla                                                                                     |
| ------------------- | ------------------------ | ----------------------------------------------------------------------------------------- |
| Función             | `fn_`                    | `SECURITY INVOKER` y `search_path` fijo                                                   |
| Procedimiento       | `sp_`                    | Solo para tareas por lotes; se invoca con `CALL` fuera de una transacción                 |
| Trigger             | `trg_`                   | `BEFORE` por fila para validar o completar; `AFTER` por fila para propagar a otras tablas |
| Vista               | `v_`                     | `security_invoker = true`, para que apliquen las políticas de quien consulta              |
| Vista materializada | `mv_`                    | Índice único para refrescar con `CONCURRENTLY`; se consulta a través de una vista         |
| Política            | `<tabla>_soda_isolation` | Una por tabla de una soda, con la misma expresión en `USING` y `WITH CHECK`               |

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

## Seguridad a nivel de fila

Es la segunda barrera del aislamiento entre sodas. La primera está en el código: cada repositorio filtra por `soda_id`. Si una consulta olvida ese filtro, PostgreSQL igual devuelve solo las filas de la soda en curso.

### Qué protege

`categories`, `dishes`, `schedules` y `closures` tienen la seguridad a nivel de fila habilitada y forzada, con una política por tabla:

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

### Excepciones

| Tabla   | Por qué no tiene política                                                                                                         |
| ------- | --------------------------------------------------------------------------------------------------------------------------------- |
| `users` | Tiene `soda_id`, pero el ingreso busca a la persona por su correo antes de saber a qué soda pertenece, y un cliente no tiene soda |
| `sodas` | No tiene `soda_id`: cada fila es una soda, y el menú público la busca por el identificador de la URL                              |

### Llaves únicas y foráneas

PostgreSQL revisa las llaves únicas, las restricciones de exclusión y las llaves foráneas sin aplicar las políticas. Por eso todas incluyen `soda_id`. Una llave única sin la soda rechazaría un nombre que ya usa otra soda y revelaría que existe; una llave foránea sin la soda permitiría que un plato apunte a la categoría de otra soda.

### Cómo agregar una tabla de una soda

1. La tabla lleva `soda_id`, y sus llaves únicas y foráneas lo incluyen.
2. Una migración habilita y fuerza la seguridad a nivel de fila y crea la política `<tabla>_soda_isolation` con la misma expresión en `USING` y `WITH CHECK`; su `down()` deshace los tres pasos.
3. Las rutas que la usan pasan por el middleware `soda`, y su fábrica usa el trait `CreatesRowsOfASoda`.
4. `RowLevelSecurityTest` revisa el catálogo de PostgreSQL y falla si una tabla con `soda_id`, salvo `users`, no tiene la seguridad habilitada, forzada y con su política.

## Reparto de responsabilidades

El dominio, en PHP, decide las reglas del negocio. PostgreSQL garantiza la integridad de los datos, el aislamiento entre sodas y las consultas por conjuntos. Ningún caso de uso se duplica en PL/pgSQL.

Todo objeto de la base se usa desde `Infrastructure/Persistence`, detrás de un puerto. El dominio y los casos de uso no saben que existen.
