# ADR 0001: Arquitectura hexagonal con diseño guiado por el dominio

Estado: aceptada.

## Contexto

SodaYa crecerá durante todo el curso: catálogo, pedidos, pagos, avisos y lectura con IA. Las reglas del negocio deben poder leerse, probarse y cambiarse sin arrastrar el framework ni la base de datos.

## Decisión

El código del producto vive en `src/` y se organiza en tres capas: `Domain`, `Application` e `Infrastructure`. Las dependencias apuntan hacia adentro. El dominio declara puertos (interfaces) y la infraestructura los implementa con adaptadores.

## Alternativas descartadas

- **MVC de Laravel con la lógica en modelos y controladores.** Es más rápido al inicio, pero las reglas quedan repartidas y solo se prueban con base de datos.
- **Capa de servicios sobre Eloquent.** Ordena los controladores, pero el dominio sigue siendo el modelo Eloquent.

## Consecuencias

- El dominio y los casos de uso se prueban sin arrancar Laravel.
- Hay más archivos y un mapeo explícito entre entidades y modelos.
- Una prueba de arquitectura hace cumplir la regla de dependencias en cada cambio.
