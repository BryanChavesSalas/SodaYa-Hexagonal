# ADR 0009: Los pagos pasan por un puerto, con HTTP directo a Stripe

Estado: aceptada.

## Contexto

SodaYa cobra los pedidos por adelantado con Stripe en modo de prueba (IS-01). La ERS exige una interfaz propia por cada proveedor externo, resuelta por el contenedor (RD-08); que los proveedores de pago se consuman por HTTP directo con el cliente `Http` de Laravel, sin SDK (RD-09); 10 segundos de espera por llamada (RNF-REN-06); solo credenciales de prueba (RNF-SEG-11); y que ninguna prueba llame a un servicio real (RNF-MAN-06). El asunto pendiente P-01 preguntaba si Stripe en modo de prueba funciona desde Costa Rica sin activar la cuenta.

## Decisión

El núcleo del módulo `Billing/Payments` solo conoce el puerto `PaymentGateway`, con `provider()`, `charge(ChargeRequest)` y `status(ChargeReference)`, y objetos de valor propios: `ChargeRequest`, `ChargeResult`, `ChargeReference` y `Amount`. El adaptador `StripeGateway` habla con Stripe por HTTP directo: la llave secreta como bearer, el cuerpo como formulario, el monto en céntimos, `currency=crc`, el encabezado `Idempotency-Key` derivado del intento, `timeout(10)` y tres intentos solo ante `ConnectionException`. Un 402 es un desenlace de negocio, rechazado, con el código del banco; un timeout, un 5xx o cualquier otra respuesta fallida es `GatewayUnavailableException`, que la API publica como 503 `servicio-no-disponible` con `Retry-After: 30`. El adaptador rechaza toda llave que no empiece por `sk_test_`, y el service provider del módulo es el único lugar que nombra a Stripe. `tests/TestCase.php` activa `Http::preventStrayRequests()`: una prueba que llegue a un servicio real falla.

P-01 queda resuelto con lo visto en la clase 11: Stripe opera en modo de prueba desde Costa Rica sin activar la cuenta y acepta cobros en colones, que viajan en céntimos porque CRC no está en su lista de monedas sin decimales. Las suposiciones S-01 y S-08 de la ERS se confirman; el cobro real en modo de prueba se comprueba a mano con `pm_card_visa` y `pm_card_visa_chargeDeclined`.

## Alternativas descartadas

- **El SDK oficial `stripe/stripe-php`.** Esconde el timeout, los reintentos y la clave de idempotencia detrás de su propia configuración, trae decenas de clases que el proyecto no usa y contradice RD-09. Con HTTP directo, lo que viaja se ve y se prueba con `Http::assertSent`.
- **Llamar a Stripe desde el controlador o el caso de uso.** El núcleo conocería la URL, la llave y la forma de la respuesta; cambiar de proveedor sería reescribirlo y probarlo exigiría internet.
- **`->throw()` sobre toda respuesta que no sea 2xx.** Una tarjeta rechazada se convertiría en una excepción genérica y la API respondería 500: el rechazo dejaría de ser un desenlace con su propio tipo de problema.
- **Reintentar también los 4xx y los 5xx.** Un 402 es una decisión del banco: repetirlo vuelve a pedir el cobro. Solo la duda de red se reintenta, y la misma clave hace que Stripe devuelva el mismo cobro en vez de crear otro.
- **`ChargeResult::fromStripe()` en la aplicación, como en la diapositiva.** La forma de la respuesta de Stripe quedaría en el núcleo y agregar otro proveedor obligaría a tocarlo. La traducción vive en el adaptador, que es la capa anticorrupción.

## Consecuencias

- Cambiar de proveedor es escribir otro adaptador y cambiar un binding; el caso de uso, el controlador y sus pruebas no cambian.
- Toda prueba que toque el adaptador simula a Stripe con `Http::fake`; la suite corre sin internet.
- Cada proveedor convierte el monto a su unidad: céntimos en Stripe, texto decimal en otros. El resto del sistema sigue en colones enteros.
- La llave de prueba vive en `STRIPE_SECRET`; una llave `sk_live_` impide arrancar el adaptador.
