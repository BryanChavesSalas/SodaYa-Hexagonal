# Arquitectura de sodaya-api-hexagonal

`sodaya-api-hexagonal` combina arquitectura hexagonal (puertos y adaptadores) con diseño guiado por el dominio. El objetivo es que las reglas del negocio se puedan leer, probar y cambiar sin depender de Laravel.

## Estructura

Todo el código del producto vive en `sodaya-api-hexagonal/src/`, bajo el namespace `Src\`. La forma es siempre contexto, módulo y capa:

```
src/
├── Shared/                      Núcleo compartido entre contextos
│   ├── Domain/
│   └── Infrastructure/
└── <Contexto>/                  Catalog (platos, categorías, menú), Sodas (horario, cierres) e Identity (cuentas)
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
2. Un Form Request valida la forma de los datos antes de llegar al controlador.
3. El controlador invoca un caso de uso con un DTO de datos primitivos.
4. El caso de uso trabaja con el dominio y con los puertos.
5. El adaptador de persistencia traduce entre el dominio y Eloquent.
6. Un API Resource construye la respuesta y expone solo los campos permitidos.

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

### Alta de una soda con su dueño

El caso de uso `RegisterSoda`, del módulo `Sodas/Profile`, crea la soda y la cuenta de su dueño en una sola transacción. Colabora con otras piezas por dos puertos: `TransactionRunner`, en `Shared/Domain/Contracts`, con el adaptador `DatabaseTransactionRunner`; y `OwnerAccounts`, en `Application/Contracts` del módulo, cuyo adaptador `IdentityOwnerAccounts` ejecuta el caso de uso `RegisterStaffMember` de Identity. El dominio y la aplicación de Sodas no importan clases de Identity: solo las importa ese adaptador. Si cualquiera de los dos pasos falla, la transacción se revierte y no queda ni la soda ni la cuenta.

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
| Otra excepción del dominio, como una franja traslapada o un cierre repetido | `conflicto` | 409 |
| Excepción HTTP del framework | El tipo de su código de estado | 4xx o 5xx |
| Cualquier otra | `error-interno`, sin detalles técnicos | 500 |

El campo `instance` repite el identificador de la solicitud, que también viaja en el encabezado `X-Request-Id` y en cada línea de log.

## Contrato de la API

Scramble genera el contrato OpenAPI 3.1 desde el código. La documentación interactiva está en `/docs/api` y el contrato se versiona en `sodaya-api-hexagonal/openapi/v1.json`. Tras cambiar un endpoint hay que regenerarlo con `composer openapi`: una prueba falla si el archivo versionado difiere del código.

## Rutas

`routes/api.php` incluye el archivo de rutas de cada módulo. Todas se sirven bajo `/api/v1`, prefijo que se configura una sola vez en `bootstrap/app.php`. Un cambio incompatible se publica como `/api/v2`.

## Pruebas

| Carpeta | Alcance |
| --- | --- |
| `tests/Unit` | Dominio y casos de uso, sin arrancar el framework. |
| `tests/Feature` | Endpoints y adaptadores contra PostgreSQL, con el rol de la aplicación. |
| `tests/Architecture` | Regla de dependencias entre capas y entre módulos. |

## Convenciones

Las convenciones de idioma y de nombres están en [ubiquitous-language.md](ubiquitous-language.md).

## Base de datos y decisiones

Los roles, las migraciones y las convenciones del esquema están en [database.md](database.md); el modelo de datos, en [data-model.md](data-model.md). El motivo de cada decisión de arquitectura está en [adr/](adr/) y los patrones usados, en [patterns.md](patterns.md).
