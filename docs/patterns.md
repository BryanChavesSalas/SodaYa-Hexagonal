# Patrones de diseño

Ningún patrón se usa por tenerlo. Cada uno resuelve un problema concreto del proyecto; si el problema no existe, el patrón tampoco.

## Patrones adoptados

| Patrón | Dónde vive | Problema que resuelve |
| --- | --- | --- |
| Puertos y adaptadores | `Domain/Contracts` e `Infrastructure` | El negocio no depende del framework ni de la base de datos |
| Repository | Contrato en `Domain/Contracts`, adaptador en `Infrastructure/Persistence/Repositories` | Guardar y recuperar agregados sin exponer la persistencia |
| Data Mapper | Dentro de cada repositorio Eloquent | Separar la entidad del dominio del modelo Eloquent |
| Aggregate | `Domain/Entities` | Un solo punto de entrada para cambiar un conjunto de datos que se valida junto |
| Value Object | `Domain/ValueObjects` | La regla de un dato vive en un solo lugar; es imposible crear uno inválido |
| Factory Method | `create()` y `reconstitute()` en cada agregado | Distinguir el nacimiento de un agregado de su lectura desde la base |
| DTO / Command | `Application/DTOs` | Cruzar capas con datos simples, sin `Request` ni modelos |
| Inyección de dependencias | Proveedor de servicios de cada módulo | Elegir el adaptador de cada puerto en un solo lugar |
| Modelo de lectura (CQRS simple) | Módulos de consulta, en `Infrastructure/Persistence/Queries` | Consultas a la medida de la pantalla sin cargar agregados |
| Cadena de responsabilidad | Middleware HTTP | Tratar cada petición antes y después del controlador |

## Patrones previstos

| Patrón | Módulo | Para qué |
| --- | --- | --- |
| Policy | Cuentas y roles | Decidir quién puede hacer qué |
| Transacción y bloqueo pesimista | Pedidos | Que dos pedidos no vendan la misma última porción |
| Estado como enum con transiciones | Pedidos | Impedir un cambio de estado inválido |
| Observer (eventos de dominio) | Pedidos y avisos | Avisar a otros contextos sin acoplarlos |
| Strategy | Avisos y pagos | Cambiar de canal o de pasarela sin tocar el caso de uso |
| Capa anticorrupción | Pagos | Que el modelo de la pasarela no contamine el dominio |
| Clave de idempotencia | Pedidos y pagos | Que un reintento no cree un segundo pedido ni un segundo cobro |
| Decorator | Caché del menú | Agregar caché sin modificar el lector |

## Descartados

| Patrón | Motivo |
| --- | --- |
| Bus de comandos y manejadores | El controlador llama al caso de uso directamente |
| Repositorio base genérico | Obliga a implementar operaciones que el módulo no usa |
| Una clase por estado (State) | Un enum con sus transiciones válidas cubre el caso |
| Specification y Abstract Factory | No hay un problema en el proyecto que los pida |
| Event Sourcing | Su complejidad supera lo que el negocio necesita |
| Capa `Presentation` separada | En una API el controlador es un adaptador más de la infraestructura |
