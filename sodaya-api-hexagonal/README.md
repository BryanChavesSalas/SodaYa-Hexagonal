# sodaya-api-hexagonal

API REST de SodaYa. Laravel 13, PHP 8.4 y PostgreSQL.

## Requisitos

| Herramienta | Versión |
| --- | --- |
| PHP | 8.4 o superior, con las extensiones `pdo_pgsql`, `intl` y `mbstring` |
| Composer | 2.x |
| PostgreSQL | 18 o superior, con la extensión `btree_gist` del paquete `contrib` |

## Instalación

1. Clonar el repositorio y entrar al proyecto:

   ```bash
   git clone https://github.com/BryanChavesSalas/SodaYa-Hexagonal.git
   cd SodaYa-Hexagonal/sodaya-api-hexagonal
   ```

2. Crear los tres roles y las dos bases de datos, la de desarrollo y la de pruebas. Se hace una sola vez, con un superusuario y con contraseñas propias:

   ```bash
   psql -U postgres -v owner_password='una-clave' -v app_password='otra-clave' -v maintenance_password='tercera-clave' -f database/roles.sql
   ```

   En un servidor que ya tenía los dos primeros roles, se crea solo el de mantenimiento:

   ```bash
   psql -U postgres -c "CREATE ROLE sodaya_maintenance LOGIN BYPASSRLS PASSWORD 'tercera-clave'" -c "GRANT CONNECT ON DATABASE sodaya_hexagonal, sodaya_hexagonal_testing TO sodaya_maintenance"
   ```

3. Instalar las dependencias y crear el archivo de entorno:

   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

4. Escribir en `.env` las tres contraseñas del paso 2:

   ```dotenv
   DB_PASSWORD=otra-clave
   DB_ADMIN_PASSWORD=una-clave
   DB_MAINTENANCE_PASSWORD=tercera-clave
   ```

5. Crear el esquema con el rol dueño, cargar los datos de demostración y levantar el servidor:

   ```bash
   php artisan migrate --database=pgsql_admin
   php artisan db:seed
   php artisan storage:link
   php artisan serve
   ```

   `storage:link` publica la carpeta `storage/app/public` en `public/storage`, de donde se sirven las fotos de los platos y los logos.

La API queda en `http://localhost:8000/api/v1` y la documentación interactiva en `http://localhost:8000/docs/api`.

La aplicación se conecta con `sodaya_app`, que no puede cambiar el esquema; las migraciones corren con `sodaya_owner`, y las tareas que recorren todas las sodas, con `sodaya_maintenance`. El motivo está en [docs/database.md](../docs/database.md).

Las tablas de una soda tienen seguridad a nivel de fila forzada: ni siquiera `sodaya_owner` ve sus filas sin fijar antes la soda de la transacción. `sodaya_app` no es dueño de las tablas ni tiene `BYPASSRLS`, y nunca se le debe otorgar. Para revisar a mano los platos de una soda en `psql`:

```sql
BEGIN;
SELECT set_config('app.soda_id', '0192f0c4-0000-7000-8000-000000000001', true);
SELECT name, price FROM dishes;
COMMIT;
```

## Comandos

| Comando | Qué hace |
| --- | --- |
| `php artisan test` | Ejecuta las pruebas contra la base `sodaya_hexagonal_testing`. |
| `composer lint` | Verifica el estilo con Laravel Pint, sin modificar archivos. |
| `composer format` | Corrige el estilo. |
| `composer analyse` | Análisis estático con Larastan en nivel 8. |
| `composer openapi` | Regenera el contrato `openapi/v1.json`. |
| `composer audit` | Revisa vulnerabilidades conocidas en las dependencias. |
| `php artisan queue:work` | Ejecuta los trabajos de la cola: avisos, reembolsos y puntos. En desarrollo se deja corriendo en otra terminal. |
| `php artisan queue:failed` | Lista los trabajos que agotaron sus intentos; `php artisan queue:retry all` los vuelve a encolar. |
| `php artisan schedule:work` | Ejecuta las tareas programadas en desarrollo; en producción se usa `schedule:run` desde cron, cada minuto. |
| `php artisan sodaya:reset-daily-portions` | Devuelve las porciones de cada plato a su cantidad base. Corre sola cada día a las 5:00, hora de Costa Rica. |
| `php artisan sodaya:expire-orders` | Cancela los pedidos cuyo plazo de pago o de confirmación ya pasó. Corre sola cada minuto. |
| `php artisan sodaya:register-soda` | Registra una soda y la cuenta de su dueño. Ver [Registrar una soda](#registrar-una-soda). |

Antes de abrir un pull request deben pasar `composer lint`, `composer analyse` y `php artisan test`. Si cambió un endpoint, hay que regenerar el contrato con `composer openapi` y versionarlo.

## Registrar una soda

La API no ofrece rutas para crear sodas ni cuentas: se registran desde la consola. Un solo comando crea la soda y la cuenta de su dueño; si alguno de los dos pasos falla, no queda ninguno.

```bash
php artisan sodaya:register-soda "Soda La Esquina" "Ana Mora" ana@sodaya.test --payment-account=ID_DE_LA_CUENTA
```

| Parámetro | Qué es |
| --- | --- |
| `name` | Nombre de la soda, de 120 caracteres como máximo. |
| `owner-name` | Nombre del dueño. |
| `owner-email` | Correo del dueño, con el que ingresa. |
| `--payment-account=` | Identificador de la cuenta de pago de la soda. Es opcional. |
| `--password=` | Contraseña del dueño, de 8 caracteres como mínimo. Si se omite, el comando la pide sin mostrarla; si el terminal no puede ocultar la entrada, el comando falla y debe usarse `--password`. |

El comando devuelve 0 si registra la soda y 1 si rechaza algún dato, con el motivo en pantalla. Sin `--password` y sin terminal interactiva rechaza la contraseña. Una contraseña escrita en `--password` queda en el historial del terminal; para uso manual conviene dejar que el comando la pida.

## Endpoints

| Método | Ruta | Quién | Descripción |
| --- | --- | --- | --- |
| GET | `/api/v1` | Cualquiera | Nombre y versión de la API. |
| GET | `/api/v1/sodas/{soda}/platos` | Visitante | Menú público de una soda, con el indicador `abierta`. |
| GET | `/api/v1/sodas/{soda}/platos/{plato}` | Visitante | Detalle de un plato del menú. |
| POST | `/api/v1/tokens` | Cualquiera | Ingresa con correo, contraseña y nombre del dispositivo; devuelve un token Bearer con las abilities del rol. |
| DELETE | `/api/v1/tokens/actual` | Persona autenticada | Cierra la sesión en este dispositivo: revoca el token de la petición. |
| DELETE | `/api/v1/tokens` | Persona autenticada | Cierra la sesión en todos los dispositivos: revoca todos sus tokens. |
| GET | `/api/v1/cocina/platos` | Personal | Platos de la soda, activos e inactivos. |
| POST | `/api/v1/cocina/platos` | Dueño | Crea un plato. `porciones_base` es opcional y, si falta, toma el valor de `porciones_disponibles`. |
| PATCH | `/api/v1/cocina/platos/{plato}` | Dueño | Edita o desactiva un plato. Cambiar `porciones_base` no cambia las porciones disponibles de hoy. |
| POST | `/api/v1/cocina/platos/{plato}/foto` | Dueño | Sube la foto de un plato: JPG, PNG o WebP de hasta 2 MB y 4000 × 4000 píxeles. |
| GET | `/api/v1/cocina/categorias` | Personal | Categorías de la soda. |
| POST | `/api/v1/cocina/categorias` | Dueño | Crea una categoría. |
| PATCH | `/api/v1/cocina/categorias/{categoria}` | Dueño | Renombra una categoría. |
| DELETE | `/api/v1/cocina/categorias/{categoria}` | Dueño | Elimina una categoría. |
| GET | `/api/v1/cocina/horario` | Personal | Franjas del horario, ordenadas por día y hora de apertura. |
| POST | `/api/v1/cocina/horario` | Dueño | Agrega una franja; el día 1 es lunes y el 7 es domingo. |
| DELETE | `/api/v1/cocina/horario/{franja}` | Dueño | Elimina una franja del horario. |
| GET | `/api/v1/cocina/cierres` | Personal | Cierres excepcionales de hoy en adelante. |
| POST | `/api/v1/cocina/cierres` | Dueño | Registra un cierre para una fecha. |
| POST | `/api/v1/cocina/cierres/hoy` | Dueño | Cierra la soda por el resto del día. |
| DELETE | `/api/v1/cocina/cierres/{cierre}` | Dueño | Elimina un cierre. |
| POST | `/api/v1/cocina/logo` | Dueño | Sube el logo de la soda, con las mismas reglas que las fotos. |
| POST | `/api/v1/sodas/{soda}/pedidos` | Cliente | Hace un pedido en una soda abierta: de 1 a 20 líneas con `plato_id` y `cantidad`; el nombre y el precio salen del catálogo. Responde 201 con `Location`. |
| PATCH | `/api/v1/cocina/pedidos/{pedido}/estado` | Personal | Avanza un pedido al estado siguiente: `confirmado`, `listo` o `entregado`. |
| POST | `/api/v1/cocina/pedidos/{pedido}/cancelacion` | Personal | Cancela un pedido pendiente o confirmado; las porciones vuelven al menú. |
| GET | `/api/v1/puntos` | Cliente | Saldo de puntos del cliente autenticado y sus últimos 20 movimientos. |
| POST | `/api/v1/puntos/canjes` | Cliente | Canjea puntos del cliente autenticado; responde el movimiento y el `saldo` que queda. |
| GET | `/api/v1/cocina/reportes/ventas-por-hora` | Dueño | Pedidos entregados y total vendido por hora de una fecha (`fecha`, por defecto hoy), con un mínimo de pedidos por hora (`minimo_pedidos`, por defecto 1). |

Con los datos de demostración, el menú de la soda de ejemplo se consulta así:

```bash
curl http://localhost:8000/api/v1/sodas/0192f0c4-0000-7000-8000-000000000001/platos
```

La respuesta incluye `abierta`, que se calcula en cada consulta con el horario y los cierres de la soda, en hora de Costa Rica. Los datos de demostración traen un horario de lunes a sábado.

El token de `POST /api/v1/tokens` se muestra una sola vez y se envía en cada petición en el encabezado `Authorization: Bearer <token>`. Un correo inexistente, una contraseña incorrecta y una cuenta desactivada reciben la misma respuesta 401 `credenciales-invalidas`. Un token revocado, o una petición sin token a una ruta protegida, recibe 401 `no-autenticado`. En `/docs/api` el botón de autorización permite probar las rutas protegidas con el token.

Los listados del personal van paginados: `?pagina=2&por_pagina=30` pide la segunda página de 30 elementos. Por defecto son 15 y el máximo es 50. La respuesta trae la página en `meta`:

```json
{"data": [], "meta": {"pagina": 2, "por_pagina": 30, "total": 41, "ultima_pagina": 2}}
```

Los endpoints de `/api/v1/cocina` piden un token Bearer; sin él responden 401 `no-autenticado`. Trabajan sobre la soda de la persona autenticada: nunca la toman de la URL, de los parámetros ni del cuerpo. Una persona sin soda, como un cliente, recibe 403 `prohibido`.

Las abilities del token determinan qué puede hacer cada miembro del personal. La ability `cocina` permite consultar platos, categorías, horario y cierres, y avanzar o cancelar los pedidos, mientras que `administrar` permite crear, editar o eliminar esos recursos y consultar los reportes. El personal de cocina recibe solo `cocina`; el dueño recibe `cocina` y `administrar`. El cliente recibe `pedidos`, que piden las rutas de `/api/v1/puntos`; esas rutas toman al cliente del token y nunca de la URL ni del cuerpo.

Los datos de demostración traen, solo para desarrollo local, dos cuentas de la soda de ejemplo, `duena@sodaya.test` con el rol de dueño y `cocina@sodaya.test` con el rol de cocina, y una cuenta de cliente, `cliente@sodaya.test`, con tres pedidos en esa soda: uno entregado, uno confirmado y uno cancelado. Las tres cuentas usan la contraseña `password`. Para ingresar como la dueña:

```bash
curl -X POST http://localhost:8000/api/v1/tokens \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"correo":"duena@sodaya.test","contrasena":"password","dispositivo":"Computadora"}'
```

La respuesta `201` trae el token en `data.token`. Con él se consultan los platos de la soda:

```bash
curl http://localhost:8000/api/v1/cocina/platos -H 'Accept: application/json' -H 'Authorization: Bearer <token>'
```

`php artisan db:seed` nunca se ejecuta en producción: crea una soda ficticia y cuentas con una contraseña conocida.

## Variables de entorno

`.env.example` lista todas las variables que lee la aplicación. Las propias del producto:

| Variable | Uso |
| --- | --- |
| `APP_TIMEZONE` | Zona horaria de la aplicación y de la sesión de PostgreSQL. Por defecto, `America/Costa_Rica`. |
| `DAILY_PORTIONS_RESET_AT` | Hora de Costa Rica en que las porciones vuelven a su cantidad base. Por defecto, `05:00`. |
| `MEDIA_DISK` | Disco de `config/filesystems.php` donde se guardan las fotos y los logos. Por defecto, `public`. |

El código nunca lee variables de entorno directamente: lo hacen los archivos de `config/`. Una prueba lo verifica.

## Estructura

```
app/         Arranque del framework
config/      Configuración: aplicación, autenticación (auth.php, sanctum.php), base de datos y contrato
database/    Roles, migraciones, factories y seeders
lang/        Mensajes al usuario en español
openapi/     Contrato OpenAPI versionado
routes/      Punto de entrada de las rutas de cada módulo
src/         Código del producto por contexto y módulo: Domain, Application e Infrastructure
tests/       Pruebas unitarias, de funcionalidad y de arquitectura
```

La arquitectura se describe en [docs/architecture.md](../docs/architecture.md).
