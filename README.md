# Finkok CFDI Bundle

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
composer require finkok/cfdi-bundle
```

Registra el bundle en `config/bundles.php` (con Symfony Flex suele hacerse solo):

```php
return [
    // …
    Finkok\CfdiBundle\FinkokBundle::class => ['all' => true],
];
```

## Configuración

```yaml
# config/packages/finkok.yaml
finkok:
    # Perfil que se usa cuando una llamada no indica uno explícitamente.
    default_profile: matriz

    profiles:
        matriz:
            username: '%env(FINKOK_USERNAME)%'
            password: '%env(FINKOK_PASSWORD)%'
            taxpayer_id: 'EKU9003173C9'   # RFC emisor; obligatorio para cancelar
            environment: demo             # demo | production

        sucursal:
            username: '%env(FINKOK_USERNAME)%'
            password: '%env(FINKOK_PASSWORD)%'
            taxpayer_id: 'MISC491214B86'
            environment: production
            # CSD opcional: solo se usa en el método cancel
            certificate: '%kernel.project_dir%/var/csd/sucursal.cer'
            private_key: '%kernel.project_dir%/var/csd/sucursal.key'
            private_key_passphrase: '%env(CSD_PASSPHRASE)%'

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

```dotenv
# .env.local
FINKOK_USERNAME="tu-usuario@empresa.com"
FINKOK_PASSWORD="tu-contraseña"
```

Las URLs por defecto son las oficiales de Finkok y se pueden sobrescribir de forma
global o por perfil:

```yaml
finkok:
    endpoints:
        stamp:
            demo: 'https://demo-facturacion.finkok.com/servicios/soap/stamp'
            production: 'https://facturacion.finkok.com/servicios/soap/stamp'
        cancel:
            demo: 'https://demo-facturacion.finkok.com/servicios/soap/cancel'
            production: 'https://facturacion.finkok.com/servicios/soap/cancel'
    profiles:
        matriz:
            # …
            endpoints:                       # este perfil sale por un proxy interno
                stamp:
                    production: 'https://proxy.interno/finkok/stamp'
```

## Uso

Los servicios se inyectan por su interfaz:

```php
use Finkok\CfdiBundle\Contract\CancelServiceInterface;
use Finkok\CfdiBundle\Contract\StampServiceInterface;

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
use Finkok\CfdiBundle\Xml\CfdiDocument;

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
use Finkok\CfdiBundle\Model\CancellationReason;
use Finkok\CfdiBundle\Model\CancellationUuid;

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
use Finkok\CfdiBundle\Model\AcceptRejectAnswer;

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
use Finkok\CfdiBundle\Model\ReceiptType;

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
use Finkok\CfdiBundle\Config\CredentialsProviderInterface;

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
use Finkok\CfdiBundle\Model\ErrorCode;

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
use Finkok\CfdiBundle\Exception\FinkokExceptionInterface;
use Finkok\CfdiBundle\Exception\TransportException;
use Finkok\CfdiBundle\Exception\ApiException;

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
| `finkok.credentials_provider` | `Config\CredentialsProviderInterface` |
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
- **`CredentialsProviderInterface`**: resuelve los perfiles; puedes decorarlo para
  cargar credenciales desde base de datos o un secret manager.
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

## Licencia

MIT. Ver [LICENSE](LICENSE).
