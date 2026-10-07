# sodaya-api-hexagonal

API REST de SodaYa. Laravel 13, PHP 8.4 y PostgreSQL.

## Requisitos

| Herramienta | Versión |
| --- | --- |
| PHP | 8.4 o superior, con las extensiones `pdo_pgsql`, `intl` y `mbstring` |
| Composer | 2.x |
| PostgreSQL | 18 o superior |

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

Antes de abrir un pull request deben pasar `composer lint`, `composer analyse` y `php artisan test`. Si cambió un endpoint, hay que regenerar el contrato con `composer openapi` y versionarlo.

## Endpoints

| Método | Ruta | Quién | Descripción |
| --- | --- | --- | --- |
| GET | `/api/v1` | Cualquiera | Nombre y versión de la API. |
| GET | `/api/v1/sodas/{soda}/platos` | Visitante | Menú público de una soda. |
| GET | `/api/v1/sodas/{soda}/platos/{plato}` | Visitante | Detalle de un plato del menú. |
| GET | `/api/v1/cocina/platos` | Personal | Platos de la soda, activos e inactivos. |
| POST | `/api/v1/cocina/platos` | Dueño | Crea un plato. |
| PATCH | `/api/v1/cocina/platos/{plato}` | Dueño | Edita o desactiva un plato. |
| GET | `/api/v1/cocina/cierres` | Personal | Cierres excepcionales de hoy en adelante. |
| POST | `/api/v1/cocina/cierres` | Dueño | Registra un cierre para una fecha. |
| POST | `/api/v1/cocina/cierres/hoy` | Dueño | Cierra la soda por el resto del día. |
| DELETE | `/api/v1/cocina/cierres/{cierre}` | Dueño | Elimina un cierre. |

Con los datos de demostración, el menú de la soda de ejemplo se consulta así:

```bash
curl http://localhost:8000/api/v1/sodas/0192f0c4-0000-7000-8000-000000000001/platos
```

Los endpoints del personal todavía no piden autenticación: trabajan sobre la soda indicada en `SODAYA_DEFAULT_SODA_ID`. La autenticación y la soda del usuario llegan en la clase 8.

## Variables de entorno

`.env.example` lista todas las variables que lee la aplicación. Las propias del producto:

| Variable | Uso |
| --- | --- |
| `APP_TIMEZONE` | Zona horaria de la aplicación y de la sesión de PostgreSQL. Por defecto, `America/Costa_Rica`. |
| `SODAYA_DEFAULT_SODA_ID` | Soda sobre la que operan los endpoints del personal hasta que exista la autenticación. |

El código nunca lee variables de entorno directamente: lo hacen los archivos de `config/`. Una prueba lo verifica.

## Estructura

```
app/         Arranque del framework
config/      Configuración: aplicación, base de datos, producto y contrato
database/    Roles, migraciones, factories y seeders
lang/        Mensajes al usuario en español
openapi/     Contrato OpenAPI versionado
routes/      Punto de entrada de las rutas de cada módulo
src/         Código del producto por contexto y módulo: Domain, Application e Infrastructure
tests/       Pruebas unitarias, de funcionalidad y de arquitectura
```

La arquitectura se describe en [docs/architecture.md](../docs/architecture.md).
