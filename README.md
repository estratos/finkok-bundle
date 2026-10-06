# Finkok CFDI Bundle

[![Última versión](https://img.shields.io/packagist/v/estratos/finkok-bundle.svg?style=flat-square)](https://packagist.org/packages/estratos/finkok-bundle)
[![Descargas totales](https://img.shields.io/packagist/dt/estratos/finkok-bundle.svg?style=flat-square)](https://packagist.org/packages/estratos/finkok-bundle)
[![Integración continua](https://github.com/estratos/finkok-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/estratos/finkok-bundle/actions/workflows/ci.yml)
[![Licencia MIT](https://img.shields.io/badge/licencia-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4.svg?style=flat-square)](composer.json)
[![Symfony](https://img.shields.io/badge/symfony-7.4-000000.svg?style=flat-square)](composer.json)

Bundle de **Symfony 7.4** para consumir los Web Services SOAP de **Finkok**:
**timbrado** (`stamp.wsdl`) y **cancelación** (`cancel.wsdl`) de CFDI.

- **Sin `ext-soap`**: el transporte SOAP está construido sobre Symfony HttpClient
  (PSR-18), con los envelopes escritos a mano a partir del WSDL real. Funciona en
  instalaciones donde la extensión SOAP no está disponible, es testeable con
  `MockHttpClient` y permite inspeccionar byte a byte lo que se envía a Finkok.
- **Multi-emisor**: varios RFC, ambientes y CSD en la misma aplicación mediante
  perfiles conmutables por nombre, seguro para workers de larga vida.
- **DTOs tipados**: nunca se manipulan arreglos ni `stdClass` de la respuesta.
- **Errores tipificados**: los ~24 códigos de incidencia de Finkok están en un
  enum con descripción y pista de solución.
- **Validación previa**: evita gastar timbrados con XML mal formado (301/705), sin
  sello (CFDI40102) o de más de 1 MB.

---

## Tabla de contenido

1. [Requisitos](#requisitos)
2. [Instalación](#instalación)
3. [Configuración](#configuración)
   - [Credenciales: las aporta tu aplicación](#credenciales-las-aporta-tu-aplicación)
4. [Uso](#uso)
   - [Timbrado](#timbrado)
   - [Recuperar un comprobante ya timbrado (incidencia 307)](#recuperar-un-comprobante-ya-timbrado-incidencia-307)
   - [Cancelación](#cancelación)
   - [Confirmar la cancelación ante el SAT](#confirmar-la-cancelación-ante-el-sat)
   - [Aceptar o rechazar una cancelación](#aceptar-o-rechazar-una-cancelación)
   - [Acuses y cancelaciones pendientes](#acuses-y-cancelaciones-pendientes)
   - [Varios emisores en la misma aplicación](#varios-emisores-en-la-misma-aplicación)
5. [Métodos disponibles](#métodos-disponibles)
6. [Manejo de errores](#manejo-de-errores)
7. [Servicios y extensibilidad](#servicios-y-extensibilidad)
8. [Notas de integración](#notas-de-integración)
9. [Seguridad](#seguridad)
10. [Pruebas](#pruebas)
11. [Estado y siguientes pasos](#estado-y-siguientes-pasos)
12. [Soporte y contribución](#soporte-y-contribución)

---

## Requisitos

| Requisito | Versión |
|---|---|
| PHP | 8.2 o superior |
| Symfony | 7.4 |
| Extensiones PHP | `dom`, `libxml` |
| Extensión PHP | `openssl` (solo si usas CSD propios para cancelar) |
| **No requiere** | `ext-soap` |

## Instalación

```bash
composer require estratos/finkok-bundle
```

Con **Symfony Flex** el bundle queda registrado automáticamente, sin que tengas que
hacer nada más. Si tu aplicación no usa Flex, añádelo a mano en
`config/bundles.php`:

```php
return [
    // …
    Estratos\FinkokBundle\FinkokBundle::class => ['all' => true],
];
```

No necesitas `ext-soap`: el transporte SOAP está construido sobre Symfony
HttpClient, que ya forma parte de las dependencias del paquete.

## Configuración

El bundle configura **solo infraestructura**, nunca credenciales:

```yaml
# config/packages/finkok.yaml
finkok:
    http:
        timeout: 30          # segundos por petición
        log_payloads: false  # true escribe el envelope completo en el log (¡datos fiscales!)
        retry:
            enabled: true    # reintenta errores de transporte y códigos transitorios
            max_retries: 2

    preflight:
        enabled: true            # valida el CFDI en local antes de enviarlo
        require_signature: true  # exige el atributo Sello (evita CFDI40102)
```

Las URLs por defecto son las oficiales de Finkok y pueden sobrescribirse:

```yaml
finkok:
    endpoints:
        stamp:
            demo: 'https://demo-facturacion.finkok.com/servicios/soap/stamp'
            production: 'https://facturacion.finkok.com/servicios/soap/stamp'
        cancel:
            demo: 'https://demo-facturacion.finkok.com/servicios/soap/cancel'
            production: 'https://facturacion.finkok.com/servicios/soap/cancel'
```

### Credenciales: las aporta tu aplicación

Los perfiles **no** viven en la configuración del bundle. Los crea la aplicación
que consume los servicios y se inyectan mediante un servicio que implemente
`Estratos\FinkokBundle\Config\CredentialsProviderInterface`. Así las credenciales
pueden venir de variables de entorno, de un secret manager, de la base de datos o
del inquilino activo, sin quedar embebidas en la configuración de un paquete.

#### Opción 1: declararlos en `config/services.yaml` (sin escribir PHP)

```yaml
# config/services.yaml
services:
    # El proveedor que consume el bundle.
    Estratos\FinkokBundle\Config\CredentialsProviderInterface:
        class: Estratos\FinkokBundle\Config\CredentialsProvider
        arguments:
            $profiles:
                matriz: '@app.finkok.credentials.matriz'
                sucursal: '@app.finkok.credentials.sucursal'
            $default: matriz

    # Cada RFC emisor, con su ambiente y —si va a cancelar— su CSD.
    app.finkok.credentials.matriz:
        class: Estratos\FinkokBundle\Config\Credentials
        arguments:
            $name: matriz
            $username: '%env(FINKOK_USERNAME)%'
            $password: '%env(FINKOK_PASSWORD)%'
            $taxpayerId: '%env(FINKOK_RFC)%'
            $environment: !php/enum Estratos\FinkokBundle\Config\Environment::Demo

    app.finkok.credentials.sucursal:
        class: Estratos\FinkokBundle\Config\Credentials
        arguments:
            $name: sucursal
            $username: '%env(FINKOK_USERNAME)%'
            $password: '%env(FINKOK_PASSWORD)%'
            $taxpayerId: 'MISC491214B86'
            $environment: !php/enum Estratos\FinkokBundle\Config\Environment::Production
            # CSD opcional: solo se usa en el método cancel
            $certificate: '%kernel.project_dir%/var/csd/sucursal.cer'
            $privateKey: '%kernel.project_dir%/var/csd/sucursal.key'
            $privateKeyPassphrase: '%env(CSD_PASSPHRASE)%'
```

```dotenv
# .env.local
FINKOK_USERNAME="tu-usuario@empresa.com"
FINKOK_PASSWORD="tu-contraseña"
FINKOK_RFC="EKU9003173C9"
```

#### Opción 2: derivarlos en tiempo de ejecución

Cuando los perfiles dependen del inquilino, de un registro en base de datos o de
un secret manager, implementa la interfaz:

```php
namespace App\Finkok;

use Estratos\FinkokBundle\Config\Credentials;
use Estratos\FinkokBundle\Config\CredentialsInterface;
use Estratos\FinkokBundle\Config\CredentialsProviderInterface;
use Estratos\FinkokBundle\Config\Environment;

final class TenantCredentialsProvider implements CredentialsProviderInterface
{
    public function __construct(private readonly TenantContext $tenant)
    {
    }

    public function get(?string $name = null): CredentialsInterface
    {
        $name ??= (string) $this->tenant->activeRfc();

        return new Credentials(
            name: $name,
            username: (string) $this->tenant->get('finkok_username'),
            password: (string) $this->tenant->get('finkok_password'),
            taxpayerId: $name,
            environment: $this->tenant->isProduction() ? Environment::Production : Environment::Demo,
        );
    }

    public function has(string $name): bool
    {
        return null !== $this->tenant->find($name);
    }

    public function default(): CredentialsInterface
    {
        return $this->get();
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return $this->tenant->allRfcs();
    }
}
```

Si la aplicación no registra ningún proveedor, el contenedor compila igual y el
error aparece —con instrucciones— en cuanto se intente timbrar o cancelar.
## Uso

Los servicios se inyectan por su interfaz:

```php
use Estratos\FinkokBundle\Contract\CancelServiceInterface;
use Estratos\FinkokBundle\Contract\StampServiceInterface;

final class FacturacionService
{
    public function __construct(
        private readonly StampServiceInterface $finkokStamp,
        private readonly CancelServiceInterface $finkokCancel,
    ) {
    }
}
```

### Timbrado

```php
use Estratos\FinkokBundle\Xml\CfdiDocument;

$cfdi = CfdiDocument::fromFile('/ruta/factura.xml');  // o fromString($xml)

$receipt = $this->finkokStamp->stamp($cfdi);

if (!$receipt->isSuccess()) {
    // El mensaje incluye el código de Finkok y, en el log, la pista de solución.
    throw new \RuntimeException((string) $receipt->getErrorMessage());
}

$uuid = $receipt->uuid;                  // 7D162D12-F6B6-4BDE-BC8A-BABC4331919A
$xmlTimbrado = $receipt->getStampedXml(); // CFDI con el nodo tfd:TimbreFiscalDigital
```

`stamp()` devuelve siempre un `StampReceipt`; las incidencias de negocio **no**
lanzan excepción (la documentación de Finkok advierte que un comprobante puede
timbrarse correctamente y aun así traer incidencias). Si prefieres el
comportamiento estricto:

```php
$receipt = $this->finkokStamp->stamp($cfdi)->assertSuccess(); // lanza ApiException si falla
```

Antes de enviar nada puedes revisar el envelope exacto, sin abrir conexión:

```php
echo $this->finkokStamp->previewStampRequest($cfdi);
```

### Recuperar un comprobante ya timbrado (incidencia 307)

Si el CFDI ya estaba timbrado, Finkok responde `CodEstatus` =
«Comprobante timbrado previamente» e intenta recuperar el XML. Puede tardar unos
segundos:

```php
$receipt = $this->finkokStamp->stamp($cfdi);

if ($receipt->isPreviouslyStamped() && !$receipt->hasStampedXml()) {
    sleep(3);
    $receipt = $this->finkokStamp->stamped($cfdi); // método Stamped
}

$xmlTimbrado = $receipt->getStampedXml();
```

Para verificar que el UUID del acuse coincide con el del XML recuperado:

```php
$receipt->uuid === $receipt->uuidFromXml();
```

### Cancelación

```php
use Estratos\FinkokBundle\Model\CancellationReason;
use Estratos\FinkokBundle\Model\CancellationUuid;

$receipt = $this->finkokCancel->cancel([
    new CancellationUuid($uuid, CancellationReason::ErrorsWithoutRelation),
]);

// Motivo 01 (con relación) exige el UUID que sustituye al cancelado:
// new CancellationUuid($uuid, CancellationReason::ErrorsWithRelation, $uuidSustituto)

$receipt->assertSuccess();

foreach ($receipt->folios as $folio) {
    $folio->uuid;                 // UUID procesado
    $folio->isCancelled();        // ¿el SAT aceptó? (códigos 201/202)
    $folio->isInProcess();        // ¿quedó en proceso? (204/205)
    $folio->getStatusDescription();
}
```

> **Advertencia de Finkok**: el código `201` confirma que la *petición* se envió
> correctamente, **no** que el CFDI ya esté cancelado. Siempre hay que confirmarlo
> con el SAT (siguiente sección).

Para que la cancelación **no** se almacene en el buffer de Finkok (y evitar la
incidencia «Already en BufferCancellation»):

```php
$this->finkokCancel->cancel($uuids, storePending: false);
```

El parámetro `cer`/`key` se envía automáticamente solo si el perfil tiene CSD
configurado. Si no, Finkok usa el certificado cargado en el panel para ese RFC.

### Confirmar la cancelación ante el SAT

```php
$status = $this->finkokCancel->getSatStatus(
    uuid: $uuid,
    taxpayerId: 'EKU9003173C9',        // RFC emisor
    receiverTaxpayerId: 'MISC491214B86', // RFC receptor
    total: '1160.00',                  // ¡con los decimales exactos!
);

$status->isActive();       // Vigente
$status->isCancelled();    // Cancelado
$status->isCancellable();  // admite cancelación
$status->details?->requiresReceiverAcceptance(); // necesita aceptación del receptor
$status->details?->isListedAsEfos();             // listado EFOS 200/201
```

Si tienes el XML a la mano, no hace falta transcribir nada: `getStatusOf()` toma
UUID, RFC de emisor, RFC de receptor y total directamente del comprobante, lo que
evita el error **«N 601 La expresión impresa proporcionada no es válida»** (casi
siempre causado por un `total` mal formado):

```php
$status = $this->finkokCancel->getStatusOf(CfdiDocument::fromString($xmlTimbrado));
```

### Aceptar o rechazar una cancelación

```php
use Estratos\FinkokBundle\Model\AcceptRejectAnswer;

$result = $this->finkokCancel->acceptReject([
    'A1B2C3D4-1111-2222-3333-444455556666' => AcceptRejectAnswer::Accepted,
    'B2C3D4E5-1111-2222-3333-444455556666' => AcceptRejectAnswer::Rejected,
], receiverTaxpayerId: 'MISC491214B86');

$result->countAccepted();
$result->countRejected();
$result->accepted[0]->uuid;
```

### Acuses y cancelaciones pendientes

```php
use Estratos\FinkokBundle\Model\ReceiptType;

// Acuse de recepción (I) o de cancelación (C)
$acuse = $this->finkokCancel->getReceipt($uuid, ReceiptType::Cancellation);
$acuse->receipt;   // XML del acuse del SAT

// UUID con cancelación pendiente de un receptor
$pending = $this->finkokCancel->getPending('MISC491214B86');
$pending->uuids;
$pending->contains($uuid);

// Estado de un timbrado que quedó en la cola de Finkok
$queued = $this->finkokStamp->queryPending($uuid);
$queued->isStampedNotSent();  // "S": timbrado, aún no enviado al SAT
$queued->isFinished();        // "F": ya enviado al SAT
$queued->attempts;
```

### Varios emisores en la misma aplicación

```php
use Estratos\FinkokBundle\Config\CredentialsProviderInterface;

final class FacturacionService
{
    public function __construct(
        private readonly StampServiceInterface $finkokStamp,
        private readonly CredentialsProviderInterface $finkokCredentials,
    ) {
    }

    public function timbrarPara(string $emisor, string $xml): string
    {
        $perfil = $this->finkokCredentials->get($emisor); // 'matriz' | 'sucursal'

        $receipt = $this->finkokStamp->stamp($xml, $perfil);

        return (string) $receipt->getStampedXml();
    }
}
```

El perfil se pasa explícitamente en cada llamada. Esto es deliberado: un «perfil
activo» global en un worker de larga vida (Messenger, RoadRunner, Swoole) acaba
timbrando con el RFC equivocado.

## Métodos disponibles

### Timbrado (`StampServiceInterface`)

| Método del bundle | Operación SOAP | Descripción |
|---|---|---|
| `stamp()` | `stamp` | Timbra el CFDI y lo encola para el SAT. Recupera el XML si ya estaba timbrado (307). |
| `quickStamp()` | `quick_stamp` | Timbrado rápido para volúmenes altos. Si ya estaba timbrado devuelve error. |
| `stamped()` | `stamped` | Recupera un CFDI timbrado previamente. Sin timbre previo devuelve 603. |
| `signStamp()` | `sign_stamp` | Timbra con los CSD cargados en el panel de Finkok (719/720 si faltan). |
| `queryPending()` | `query_pending` | Estado de un comprobante que quedó en la cola de Finkok. |
| `previewStampRequest()` | — | Devuelve el envelope que enviaría `stamp()` sin abrir conexión. |

### Cancelación (`CancelServiceInterface`)

| Método del bundle | Operación SOAP | Descripción |
|---|---|---|
| `cancel()` | `cancel` | Cancela uno o varios UUID con su motivo y folio de sustitución. |
| `acceptReject()` | `accept_reject` | Acepta o rechaza solicitudes de cancelación como receptor. |
| `getSatStatus()` | `get_sat_status` | Estado del CFDI ante el SAT: Vigente/Cancelado, cancelable, EFOS. |
| `getStatusOf()` | `get_sat_status` | Igual, tomando los datos del XML para evitar el error N 601. |
| `getPending()` | `get_pending` | UUID con cancelación pendiente de un receptor. |
| `getReceipt()` | `get_receipt` | Acuse de recepción (`I`) o de cancelación (`C`). |
| `queryPendingCancellation()` | `query_pending_cancellation` | Estado de una cancelación en el buffer de Finkok. |

## Manejo de errores

Los métodos devuelven DTOs que implementan `FinkokResultInterface`:

```php
$receipt->isSuccess();             // ¿surtió efecto la operación?
$receipt->getStatusCode();         // CodEstatus tal cual lo devolvió Finkok
$receipt->getErrorCodes();         // ['705']
$receipt->getErrorMessage();       // mensaje legible, o null
$receipt->hasErrorCode(ErrorCode::InvalidXmlStructure);
$receipt->getIncidences();         // colección tipada de incidencias
$receipt->isCredentialError();     // usuario/contraseña o ambiente equivocado
$receipt->isTransient();           // conviene reintentar
$receipt->requiresManualAction();  // hay que actuar en el panel de Finkok o ante el SAT
$receipt->assertSuccess();         // lanza ApiException si no fue exitosa
```

Catálogo de códigos con descripción y pista de solución:

```php
use Estratos\FinkokBundle\Model\ErrorCode;

$code = ErrorCode::tryFrom('307');

$code?->description();  // "El CFDI contiene un timbre previo."
$code?->hint();         // qué hacer exactamente para resolverlo
$code?->isTransient();
$code?->requiresManualAction();
$code?->isAlreadyStamped();
```

Excepciones del bundle (todas implementan `FinkokExceptionInterface`):

| Excepción | Cuándo se lanza |
|---|---|
| `TransportException` | Fallo de red, timeout o HTTP 4xx/5xx sin SOAP Fault. `isRetryable()` indica si conviene reintentar. |
| `SoapFaultException` | SOAP Fault de protocolo: error de esquema, operación inexistente, URL equivocada. |
| `UnexpectedResponseException` | La respuesta no es un envelope SOAP (proxy, portal cautivo, URL equivocada). |
| `ApiException` | Incidencia de negocio, lanzada por `assertSuccess()`. Conserva `getIncidences()` y `getResult()`. |
| `ValidationException` | Validación local: XML vacío o mal formado, sin sello, mayor a 1 MB, motivo 01 sin folio de sustitución. |
| `ConfigurationException` | Perfil mal configurado (sin usuario/contraseña, certificado sin llave, sin `taxpayer_id`). |
| `ProfileNotFoundException` | Se pidió un perfil que no existe; el mensaje lista los disponibles. |

```php
use Estratos\FinkokBundle\Exception\FinkokExceptionInterface;
use Estratos\FinkokBundle\Exception\TransportException;
use Estratos\FinkokBundle\Exception\ApiException;

try {
    $receipt = $this->finkokStamp->stamp($cfdi)->assertSuccess();
} catch (TransportException $e) {
    if ($e->isRetryable()) {
        // reintentar más tarde (Messenger, cron, …)
    }
} catch (ApiException $e) {
    foreach ($e->getIncidences() as $incidencia) {
        $this->logger->error($incidencia->describe());
    }
} catch (FinkokExceptionInterface $e) {
    // cualquier otro fallo del bundle
}
```

En [`docs/errores.md`](docs/errores.md) están las tablas completas de códigos de
timbrado, cancelación y `get_sat_status`.

## Servicios y extensibilidad

| Servicio | Interfaz para autowiring |
|---|---|
| `finkok.stamp_service` | `Contract\StampServiceInterface` |
| `finkok.cancel_service` | `Contract\CancelServiceInterface` |
| `finkok.credentials_provider` | `Config\CredentialsProviderInterface` (lo aporta tu aplicación) |
| `finkok.transport` | `Soap\SoapTransportInterface` |
| `finkok.csd_encoder` | `Csd\CsdEncoderInterface` |

Puntos de extensión:

- **`SoapTransportInterface`**: sustituye el transporte (por ejemplo para añadir un
  cliente HTTP propio con mTLS o un interceptor de trazas).
- **`CsdEncoderInterface`**: cambia cómo se codifican el `.cer` y el `.key` para el
  método `cancel`. El codificador por defecto (`RawFileCsdEncoder`) hace
  `base64` del contenido del archivo **una sola vez**; si tu cuenta requiere el
  proceso antiguo de PEM + cifrado DES3 con la contraseña del panel, implementa
  esta interfaz y regístrala como `finkok.csd_encoder`.
- **`CredentialsProviderInterface`**: es el punto por el que tu aplicación aporta
  los perfiles. Si no registras ninguno, el contenedor compila pero cualquier uso
  falla con un mensaje que explica cómo registrarlo.
- **Cliente HTTP**: si la aplicación define el servicio `http_client`, el bundle lo
  reutiliza (comparte pool de conexiones, proxy y CA configurados); si no, crea
  uno propio.

## Notas de integración

Puntos que provocan la mayoría de los incidentes en producción:

1. **Límite de 1 MB por XML.** Superarlo puede afectar el timbrado de comprobantes
   posteriores. El validador previo lo detecta: `CfdiDocument::MAX_SIZE_BYTES`.
2. **Ventana de 72 horas.** El SAT solo acepta timbrar dentro de las 72 horas
   posteriores a la fecha de emisión y rechaza fechas futuras (incidencia 401).
3. **Certificados nuevos.** La lista LCO del SAT tarda hasta 72 horas en
   actualizarse (incidencias 305 y 402).
4. **`NoCertificado` y CSD.** El atributo debe corresponder al certificado usado, y
   para personas morales el certificado debe ser CSD, no FIEL (306, 712).
5. **Base64 una sola vez.** El XML del CFDI se codifica una sola vez. En el método
   `cancel`, el `.cer` y el `.key` también (doble codificación → incidencia 704).
6. **`total` en `get_sat_status`.** Debe llevar los decimales exactos del
   comprobante, o el SAT responde «N 601». Usa `getStatusOf($cfdi)`.
7. **Máximo 5 intentos de cancelación por UUID.** Al sexto, Finkok responde 799 y
   ya no se puede cancelar por ese medio.
8. **DEMO no es para cargas.** El ambiente de demostración no soporta pruebas de
   estrés; su balanceo provoca incidencias transitorias como la 709.
9. **`store_pending`.** Con el buffer activo puede aparecer «Already en
   BufferCancellation»; pasa `storePending: false` si no lo necesitas.
10. **Retimbrado en el mismo mes.** La cancelación con motivo 01 requiere el UUID
    del comprobante que sustituye al cancelado; los motivos 02, 03 y 04 no lo
    admiten (el bundle lo valida antes de enviar).

## Seguridad

- **El método `cancel` envía el CSD a Finkok.** El WSDL exige `cer` y `key` para
  firmar la solicitud. Los parámetros son `minOccurs="0"`, así que el bundle los
  **omite** si el perfil no tiene CSD configurado: en ese caso Finkok usa el
  certificado cargado en el panel para ese RFC. Es la opción recomendada.
- Si necesitas no compartir la llave privada en absoluto, la alternativa es el
  servicio `cancel_signature`, que recibe la solicitud de cancelación ya firmada
  por tu aplicación. No está implementado en esta versión (ver
  [Estado y siguientes pasos](#estado-y-siguientes-pasos)).
- `log_payloads` está **desactivado** por omisión: el envelope contiene datos
  fiscales y, al cancelar, los CSD en base64. Los logs siempre enmascaran la
  contraseña y resumen los binarios (`SoapRequest::debugArguments()`).
- `Credentials::__debugInfo()` oculta la contraseña al volcar el objeto, así que
  `dump()` y el profiler de Symfony no la exponen.

## Pruebas

```bash
# Suite offline (sin red): 186 pruebas
vendor/bin/phpunit

# Prueba de contrato contra los servidores DEMO y PRODUCCIÓN reales de Finkok.
# No necesita credenciales: verifica que Finkok entiende el envelope y responde
# la incidencia 300 en lugar de un error de deserialización.
vendor/bin/phpunit --group live
```

La suite cubre la construcción de los envelopes (namespaces incluidos), el parseo
de respuestas reales de Finkok, la hidratación de los DTO, la validación previa,
los perfiles multi-emisor, el cableado del contenedor y los flujos de error
(300, 301, 307, 603, 705, 798, 799, `Invalid Username or Password`, SOAP Fault,
HTTP 502 y respuesta no-SOAP).

## Estado y siguientes pasos

Implementado y verificado contra los servidores de Finkok:

- Timbrado: `stamp`, `quick_stamp`, `stamped`, `query_pending`, `sign_stamp`.
- Cancelación: `cancel`, `accept_reject`, `get_sat_status`, `get_pending`,
  `get_receipt`, `query_pending_cancellation`.

Fuera del alcance de esta entrega, candidatos naturales para siguientes versiones:

- **`cancel_signature`**, `accept_reject_signature` y `get_related_signature`: las
  variantes que reciben el XML de cancelación ya firmado, sin compartir el CSD.
- Variantes `out_*` (`out_cancel`, `out_accept_reject`, `get_out_*`) para cancelar
  CFDI timbrados por otro PAC.
- Métodos asíncronos de timbrado (`stamp_async`, `get_result_async`, `get_pdf`).
- Web Service de utilidades (reportes de timbres, hora del servidor) y de registro
  de clientes (`add`, `edit`, `assign`, `switch`, `get`).
- Data collector para el profiler de Symfony con el envelope de cada llamada.

---

## Soporte y contribución

- **Reportar un error o pedir una mejora**: <https://github.com/estratos/finkok-bundle/issues>
- **Código fuente**: <https://github.com/estratos/finkok-bundle>
- **Paquete en Packagist**: <https://packagist.org/packages/estratos/finkok-bundle>
- **Códigos de error de Finkok**: [docs/errores.md](docs/errores.md)

Antes de enviar un cambio:

```bash
composer install
vendor/bin/phpunit              # 186 pruebas offline, sin red
vendor/bin/phpunit --group live # 4 pruebas de contrato contra Finkok
```

Los cambios se registran en [CHANGELOG.md](CHANGELOG.md) siguiendo
[Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/), y el proyecto usa
[Versionado Semántico](https://semver.org/lang/es/).

---

## Licencia

MIT. Ver [LICENSE](LICENSE).
