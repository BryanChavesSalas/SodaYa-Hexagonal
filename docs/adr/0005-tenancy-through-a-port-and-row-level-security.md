# ADR 0005: Multi-inquilino por un puerto y por seguridad a nivel de fila

Estado: aceptada. La segunda barrera se implementa en el módulo multi-inquilino.

## Contexto

Varias sodas comparten la misma instalación y la misma base. Un olvido en una consulta no puede exponer los datos de otra soda.

## Decisión

Hay dos barreras. La primera está en el código: el puerto `SodaContext` responde a qué soda pertenece la operación, y cada repositorio filtra por ese `SodaId`. La segunda está en PostgreSQL: políticas de seguridad a nivel de fila sobre toda tabla con `soda_id`. Para que la segunda barrera sirva, la aplicación se conecta con un rol que no es dueño de las tablas.

## Alternativas descartadas

- **Tomar la soda del cuerpo o de la URL de la petición.** Cualquiera podría operar sobre la soda de otro.
- **Una base o un esquema por soda.** Resuelve un problema de escala que el proyecto no tiene y complica las migraciones.
- **Solo un filtro global de Eloquent.** Es una sola barrera y se desactiva con una llamada.

## Consecuencias

- Cambiar de dónde sale la soda (configuración, usuario autenticado) afecta a un solo adaptador.
- Toda llave única y foránea incluye `soda_id`.
- Las migraciones corren con un rol y la aplicación con otro.
