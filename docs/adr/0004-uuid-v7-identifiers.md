# ADR 0004: Identificadores UUID versión 7

Estado: aceptada.

## Contexto

Un identificador autoincremental lo asigna la base: el agregado no tendría identidad hasta guardarse. Además, en una API pública `/platos/41` revela cuántos registros hay y permite recorrerlos.

## Decisión

Toda llave primaria es un UUID versión 7. La aplicación lo genera antes de guardar, a través del repositorio. La columna tiene `DEFAULT uuidv7()`, función nativa de PostgreSQL 18, como respaldo. Cada identificador tiene su propio tipo en el dominio (`SodaId`, `DishId`).

## Alternativas descartadas

- **Enteros autoincrementales.** Enumerables y dependientes de la base.
- **UUID versión 4.** Aleatorio: fragmenta el índice primario al insertar.
- **ULID.** Equivalente en orden, pero no es un tipo nativo de PostgreSQL.

## Consecuencias

- Los identificadores están ordenados por tiempo y no revelan volumen.
- El analizador estático rechaza pasar el identificador de un plato donde se espera el de una soda.
