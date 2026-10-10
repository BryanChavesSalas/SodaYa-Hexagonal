# SodaYa-Hexagonal

Plataforma multi-inquilino donde varias sodas publican su menú del día y reciben pedidos para llevar. Es el proyecto guía del curso ISW-621 Programación en Ambiente Web II (Universidad Técnica Nacional, sede San Carlos).

[![CI](https://github.com/BryanChavesSalas/SodaYa-Hexagonal/actions/workflows/ci.yml/badge.svg)](https://github.com/BryanChavesSalas/SodaYa-Hexagonal/actions/workflows/ci.yml)

## Contenido del repositorio

| Ruta | Contenido |
| --- | --- |
| [`sodaya-api-hexagonal/`](sodaya-api-hexagonal/) | API REST en Laravel 13 sobre PostgreSQL 18. |
| [`docs/architecture.md`](docs/architecture.md) | Estructura, capas, regla de dependencias, errores y contrato. |
| [`docs/database.md`](docs/database.md) | Roles, migraciones y convenciones de la base de datos. |
| [`docs/data-model.md`](docs/data-model.md) | Modelo de datos en tercera forma normal. |
| [`docs/ubiquitous-language.md`](docs/ubiquitous-language.md) | Glosario del negocio y convenciones de nombres. |
| [`docs/patterns.md`](docs/patterns.md) | Patrones de diseño usados, previstos y descartados. |
| [`docs/adr/`](docs/adr/) | Registro de las decisiones de arquitectura. |
| [`.github/`](.github/) | Integración continua, plantillas y Dependabot. |

## Arquitectura

`sodaya-api-hexagonal` combina arquitectura hexagonal con diseño guiado por el dominio. El código del producto vive en `sodaya-api-hexagonal/src/`, organizado por contexto y módulo y, dentro de cada módulo, en tres capas:

```
Infrastructure  ──▶  Application  ──▶  Domain
```

El dominio no depende de Laravel y una prueba de arquitectura lo verifica en cada cambio. La aplicación se conecta a PostgreSQL 18 con un rol que no es dueño de las tablas. El detalle está en [docs/architecture.md](docs/architecture.md) y [docs/database.md](docs/database.md).

## Estado

Construido hasta la clase 7 del curso:

| Clase | Entrega |
| --- | --- |
| 2 | Proyecto base: Laravel 13, PostgreSQL, zona horaria de Costa Rica y mensajes en español. |
| 3 | Arquitectura hexagonal en `src/`, API versionada bajo `/api/v1` y lenguaje ubicuo. |
| 4 | Catálogo de platos: dominio, esquema con restricciones, casos de uso y endpoints del personal. |
| 5 | Menú público, errores RFC 9457, `X-Request-Id` y contrato OpenAPI. |
| 6 | Pint, Larastan, integración continua, configuración por entorno y esta documentación. |
| 7 | Categorías del menú, horario de atención por franjas, cierres excepcionales e indicador `abierta` en el menú público. |

El despliegue en una dirección pública está pendiente ([#30](https://github.com/BryanChavesSalas/SodaYa-Hexagonal/issues/30)).

## Puesta en marcha

Los pasos de instalación están en [sodaya-api-hexagonal/README.md](sodaya-api-hexagonal/README.md).

## Forma de trabajo

- **Planificación:** cada requisito es un issue dentro de un milestone, con seguimiento en el [tablero del proyecto](https://github.com/BryanChavesSalas/SodaYa-Hexagonal/projects).
- **Ramas:** cada issue se trabaja en su propia rama, creada desde el issue con *Create a branch*. Sale de `main` y lleva el número y el título del issue (`12-modelar-el-dominio-del-catálogo`).
- **Commits:** [Conventional Commits](https://www.conventionalcommits.org/es/v1.0.0/), uno por cambio lógico, con la referencia al issue en el pie (`Refs: #12`).
- **Pull requests:** uno por issue, enlazado con `Closes #N`. Todo cambio entra a `main` por pull request con la integración continua en verde. Se fusiona con merge commit para conservar los commits atómicos.
- **Etiquetas:** al terminar los issues de una clase se crea la etiqueta `clase-NN` sobre `main`, de modo que se puede comparar lo que agregó cada clase:

  ```bash
  git diff clase-04 clase-05 --stat
  ```

## Idioma

El código, la base de datos y los comentarios están en inglés. Las rutas, los atributos JSON y los mensajes al usuario están en español. La convención completa está en [docs/ubiquitous-language.md](docs/ubiquitous-language.md).
