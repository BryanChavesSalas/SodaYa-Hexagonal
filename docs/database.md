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

Por qué importa: en PostgreSQL el dueño de una tabla, los superusuarios y los roles con `BYPASSRLS` no pasan por las políticas de seguridad a nivel de fila. Si la aplicación se conectara como dueña, el aislamiento entre sodas que llega en el módulo multi-inquilino no protegería nada.

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

| Objeto              | Prefijo | Regla                                                                                     |
| ------------------- | ------- | ----------------------------------------------------------------------------------------- |
| Función             | `fn_`   | `SECURITY INVOKER` y `search_path` fijo                                                   |
| Procedimiento       | `sp_`   | Solo para tareas por lotes; se invoca con `CALL` fuera de una transacción                 |
| Trigger             | `trg_`  | `BEFORE` por fila para validar o completar; `AFTER` por fila para propagar a otras tablas |
| Vista               | `v_`    | `security_invoker = true`, para que apliquen las políticas de quien consulta              |
| Vista materializada | `mv_`   | Índice único para refrescar con `CONCURRENTLY`; se consulta a través de una vista         |

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

## Reparto de responsabilidades

El dominio, en PHP, decide las reglas del negocio. PostgreSQL garantiza la integridad de los datos, el aislamiento entre sodas y las consultas por conjuntos. Ningún caso de uso se duplica en PL/pgSQL.

Todo objeto de la base se usa desde `Infrastructure/Persistence`, detrás de un puerto. El dominio y los casos de uso no saben que existen.
