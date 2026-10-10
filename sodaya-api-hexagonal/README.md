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

2. Crear los dos roles y las dos bases de datos, la de desarrollo y la de pruebas. Se hace una sola vez, con un superusuario y con contraseñas propias:

   ```bash
   psql -U postgres -v owner_password='una-clave' -v app_password='otra-clave' -f database/roles.sql
   ```

3. Instalar las dependencias y crear el archivo de entorno:

   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

4. Escribir en `.env` las dos contraseñas del paso 2:

   ```dotenv
   DB_PASSWORD=otra-clave
   DB_ADMIN_PASSWORD=una-clave
   ```

5. Crear el esquema con el rol dueño, cargar los datos de demostración y levantar el servidor:

   ```bash
   php artisan migrate --database=pgsql_admin
   php artisan db:seed
   php artisan serve
   ```

La API queda en `http://localhost:8000/api/v1` y la documentación interactiva en `http://localhost:8000/docs/api`.

La aplicación se conecta con `sodaya_app`, que no puede cambiar el esquema; las migraciones corren con `sodaya_owner`. El motivo está en [docs/database.md](../docs/database.md).

## Comandos

| Comando | Qué hace |
| --- | --- |
| `php artisan test` | Ejecuta las pruebas contra la base `sodaya_hexagonal_testing`. |
| `composer lint` | Verifica el estilo con Laravel Pint, sin modificar archivos. |
| `composer format` | Corrige el estilo. |
| `composer analyse` | Análisis estático con Larastan en nivel 8. |
| `composer openapi` | Regenera el contrato `openapi/v1.json`. |
| `composer audit` | Revisa vulnerabilidades conocidas en las dependencias. |
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
| GET | `/api/v1/cocina/platos` | Personal | Platos de la soda, activos e inactivos. |
| POST | `/api/v1/cocina/platos` | Dueño | Crea un plato. |
| PATCH | `/api/v1/cocina/platos/{plato}` | Dueño | Edita o desactiva un plato. |
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

Con los datos de demostración, el menú de la soda de ejemplo se consulta así:

```bash
curl http://localhost:8000/api/v1/sodas/0192f0c4-0000-7000-8000-000000000001/platos
```

La respuesta incluye `abierta`, que se calcula en cada consulta con el horario y los cierres de la soda, en hora de Costa Rica. Los datos de demostración traen un horario de lunes a sábado.

El token de `POST /api/v1/tokens` se muestra una sola vez y se envía en cada petición en el encabezado `Authorization: Bearer <token>`. Un correo inexistente, una contraseña incorrecta y una cuenta desactivada reciben la misma respuesta 401 `credenciales-invalidas`.

Los endpoints de `/api/v1/cocina` piden un token Bearer; sin él responden 401 `no-autenticado`. Trabajan sobre la soda de la persona autenticada: nunca la toman de la URL, de los parámetros ni del cuerpo. Una persona sin soda, como un cliente, recibe 403 `prohibido`.

Los datos de demostración traen dos cuentas de la soda de ejemplo, solo para desarrollo local: `duena@sodaya.test`, con el rol de dueño, y `cocina@sodaya.test`, con el rol de cocina. Las dos usan la contraseña `password`. Para ingresar como la dueña:

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
