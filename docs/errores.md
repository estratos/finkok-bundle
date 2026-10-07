# Códigos y comportamiento de los servicios de Finkok

Referencia de los códigos que devuelven los Web Services de **timbrado** y
**cancelación**, con la reacción recomendada y su equivalente en el bundle.

> **Regla de oro documentada por Finkok**: un comprobante puede haberse timbrado
> correctamente y aun así regresar incidencias. La integración debe decidir con
> `CodEstatus` y con la presencia del UUID, **nunca** con la sola presencia de
> incidencias.

> El proceso de cancelación lo realiza el SAT. Los servicios de Finkok solo
> habilitan la conexión con la autoridad.

---

## 1. Métodos del Web Service de cancelación

Permiten enviar la petición de cancelación: `cancel`, `sign_cancel` y
`cancel_signature`. El resto de métodos del servicio son `get_receipt`,
`get_sat_status`, `get_related`, `get_pending`, `accept_reject`,
`query_pending_cancellation`, `get_related_signature` y
`accept_reject_signature`.

Implementados en este bundle: `cancel`, `accept_reject`, `get_sat_status`,
`get_pending`, `get_receipt` y `query_pending_cancellation`.

---

## 2. Validación de la cancelación del CFDI (201-212)

Llegan en `CodEstatus` del acuse y, por UUID, en `EstatusUUID` de cada `Folio`.
Se tipifican en `Estratos\FinkokBundle\Model\CancellationStatusCode`.

| Código | Significado | Cómo reaccionar | Enum |
|---|---|---|---|
| 201 | Petición de cancelación realizada exitosamente | **No garantiza la cancelación**: confírmala con `get_sat_status` | `RequestAccepted` |
| 202 | UUID previamente cancelado | No reintentes; consulta el estatus | `PreviouslyCancelled` |
| 203 | No encontrado o no corresponde en el emisor | Compara `taxpayer_id` con el atributo `Rfc` del nodo Emisor | `NotFoundOrIssuerMismatch` |
| 204 | UUID no aplicable para cancelación | El comprobante no admite cancelación | `NotApplicable` |
| 205 | UUID no existe | Espera de 2 a 5 minutos si acabas de timbrar; revisa que los datos correspondan al comprobante | `UuidNotFound` |
| 206 | UUID no corresponde a un CFDI del sector primario | — | `NotPrimarySector` |
| 207 | Motivo de cancelación inválido | Usa una clave del catálogo (01-04) y revisa la relación | `InvalidReason` |
| 208 | Folio de sustitución inválido | El motivo 01 exige un folio existente y vigente | `InvalidReplacementFolio` |
| 209 | Folio de sustitución no requerido | El folio de sustitución solo aplica al motivo 01 | `ReplacementFolioNotRequired` |
| 210 | La fecha de solicitud es mayor a la fecha de declaración | Solo procede hasta el 31 de enero del año siguiente (regla 2.7.1.47 de la RMF) | `RequestDateAfterDeclaration` |
| 211 | La fecha rebasa el límite para una factura global | Revisa la fecha de la factura global | `GlobalInvoiceDateLimit` |
| 212 | Relación no válida o inexistente | Revisa los CFDI relacionados | `InvalidRelation` |
| `no_cancelable` | El UUID contiene CFDI relacionados | Revisa las relaciones; la relación dura unos 30 minutos | `NotCancellable` |

**Importante sobre el 201**: la documentación de Finkok es explícita en que solo
confirma que la *petición* se realizó. Por eso el bundle no da por cancelado un
comprobante con 201 si el estatus textual no lo confirma:

```php
$folio->isRequestAccepted();  // 201, 202 u 798
$folio->isCancelled();        // definitivo: 202 o EstatusCancelacion «Cancelado»
$folio->isInProcess();        // EstatusCancelacion «En proceso»
```

---

## 3. Validaciones de las peticiones de cancelación (300-314)

| Código | Significado | Cómo reaccionar | Enum |
|---|---|---|---|
| 300 | Usuario no válido | Verifica el usuario y el ambiente. `$receipt->isCredentialError()` | `InvalidUser` |
| 301 | XML mal formado | Revisa cabeceras y nodos | `MalformedXml` |
| 302 | Sello mal formado | Intermitencia del SAT: reintenta en unos minutos | `MalformedSeal` |
| 304 | Certificado revocado o caduco | Usa un CSD vigente (no tiene que ser el de timbrado) | `RevokedOrExpiredCertificate` |
| 305 | Certificado inválido | Los certificados emitidos entre el 03-05-2023 y el 24-05-2023 presentaron errores | `InvalidCertificate` |
| 309 | Patrón de folio inválido | El UUID no es un folio fiscal o viene vacío | `InvalidFolioPattern` |
| 310 | Se está usando una FIEL y no un CSD | Cancela con el CSD del emisor | `FielInsteadOfCsd` |
| 311 | Clave de motivo de cancelación no válida | Las claves válidas son 01, 02, 03 y 04 | `InvalidReasonKey` |
| 312 | UUID no relacionado de acuerdo a la clave de motivo | Relaciona los comprobantes conforme a la clave usada | `UuidNotRelatedToReason` |
| 314 | Relación no válida | Revisa la relación entre comprobantes | `RelationNotValid` |

---

## 4. Errores propios de Finkok (cancelación)

| Código | Significado | Cómo reaccionar | Enum |
|---|---|---|---|
| 704 | El certificado o la llave se codificaron dos veces en base64 | Codifica una sola vez | `DoubleBase64Encoding` |
| 708 | No se pudo conectar al SAT | Intermitencia: reintenta más tarde. `isTransient()` | `SatUnreachable` |
| 711 | Error con el certificado al cancelar | El certificado está incompleto, mal codificado, doblemente en base64 o el base64 no lleva los encabezados | `CertificateError` |
| 798 | Ya existe una solicitud previa; hay que esperar 72 horas | Consulta el estatus y **no** mandes otra petición | `AlreadyRequested` |
| 799 | Excedieron el límite de las 5 peticiones | Ya no es posible cancelar por Finkok: solo en el portal del SAT | `TooManyAttempts` |

### Errores de texto (sin código numérico)

| `CodEstatus` / `error` | Significado | Cómo reaccionar |
|---|---|---|
| `Invalid Username or Password` | Credenciales inválidas. **El Web Service de cancelación lo reporta así**, en inglés y sin `<Incidencias>`; el de timbrado usa la incidencia 300 | `$receipt->isCredentialError()` |
| `Motivo de cancelación debe contener uno de los valores 01,02,03 o 04` | No se envió un motivo válido | Envía un `CancellationReason` |
| `Motivo de cancelación inválido` | La clave no es válida para ese comprobante | Revisa 207 y 311 |
| `La lista enviada contiene UUIDS en formato inválido` | Hay UUID vacíos o mal formados | Valida con `CancellationUuid::isValidUuid()` |
| `Invalid Passphrase` | La contraseña con la que se cifró la llave en DES3 no es la correcta, la llave o el certificado están doblemente en base64, o cambió la contraseña del panel | Revisa la sección 9 y el codificador CSD |
| `Incorrect padding` | Finkok no pudo descifrar la llave en DES3 | Contacta a soporte para que actualicen el passphrase de la cuenta |
| `Already en BufferCancellation` | La solicitud anterior quedó en el buffer de cancelaciones | Envía `storePending: false` |
| `Already Cancelleded` | Más de 3 solicitudes detectadas para el mismo UUID | Consulta el estatus con `get_sat_status` |
| `Error: Emisor XXX no tiene certificado XXXX activo asignado` | `sign_cancel` sin CSD cargados en el panel | Carga los CSD del RFC en «Clientes» |

---

## 5. Método `accept_reject` (1000-1006)

Estos códigos son **exclusivos** de la aceptación/rechazo y llegan en el campo
`status` de cada elemento `Acepta` o `Rechaza`. Se tipifican en
`Estratos\FinkokBundle\Model\AcceptRejectStatus`. No tienen nada que ver con el
rango 201-212 del método `cancel`.

| Código | Significado | Cómo reaccionar | Enum |
|---|---|---|---|
| 1000 | Se recibió la respuesta de la petición de forma exitosa | Confirma el resultado con `get_sat_status` | `ResponseReceived` |
| 1001 | No existen peticiones de cancelación en espera de respuesta | No hay nada que aceptar ni rechazar | `NoPendingRequests` |
| 1002 | Ya se recibió una respuesta para la petición | Consulta el estatus | `AlreadyAnswered` |
| 1003 | Sello no corresponde al RFC del Receptor | Firma con el CSD del receptor, no del emisor | `SealDoesNotMatchReceiver` |
| 1004 | Existen más de una petición de cancelación para el mismo UUID | Revisa el flujo de cancelación del emisor | `MultipleRequests` |
| 1005 | El UUID es nulo o no posee el formato correcto | Revisa el UUID enviado | `InvalidUuid` |
| 1006 | Se rebasó el número máximo de solicitudes permitidas | Contacta a soporte | `MaxRequestsExceeded` |

```php
foreach ($result->accepted as $entry) {
    $entry->isAcknowledged();      // código 1000
    $entry->isNothingToAnswer();   // 1001 o 1002
    $entry->getStatusDescription();
}
```

---

## 6. `get_sat_status`

| Código | Significado | Cómo reaccionar |
|---|---|---|
| `S - Comprobante obtenido satisfactoriamente` | El SAT localizó el comprobante | `$status->details?->isFound()` |
| `N - 601 La expresión impresa proporcionada no es válida` | Los parámetros no coinciden; casi siempre el `total` sin sus decimales exactos | Usa `getStatusOf($cfdi)`, que toma los datos del XML |
| `N - 602 Comprobante no encontrado` | UUID erróneo, el SAT aún no tiene registro, o los parámetros no pertenecen a ese UUID | Verifica el UUID y espera si acabas de timbrar |
| `no_cancelable` | El UUID contiene CFDI relacionados | Revisa los UUID relacionados antes de cancelar |

Campos del detalle (`SatStatusDetails`):

| Campo | Valores | Método |
|---|---|---|
| `CodigoEstatus` | `S - …` / `N …` | `isFound()` |
| `Estado` | `Vigente` / `Cancelado` | `isActive()` / `isCancelled()` |
| `EsCancelable` | `Cancelable con aceptación` / `Cancelable sin aceptación` / `No cancelable` | `isCancellable()`, `requiresReceiverAcceptance()` |
| `EstatusCancelacion` | `Cancelado`, `En proceso`, `Solicitud rechazada`, `Plazo vencido` | `cancellationStatus` |
| `ValidacionEFOS` | `100`, `101`, `200`, `201` | `isListedAsEfos()` (200/201) |

---

## 7. Incidencias del Web Service de timbrado

Llegan dentro de `<Incidencias><Incidencia>` en una respuesta correcta. Se
tipifican en `Estratos\FinkokBundle\Model\ErrorCode`.

| Código | Significado | Cómo reaccionar |
|---|---|---|
| 300 | El usuario o contraseña son inválidos | Verifica credenciales y que la URL corresponda al ambiente |
| 301 | XML mal formado | Valida en <https://validador.finkok.com> |
| 303 | Sello no corresponde a emisor | Usa el par CSD del emisor declarado |
| 304 | Certificado revocado o caduco | Consulta la lista del SAT y sustitúyelo |
| 305 | La fecha de emisión no está dentro de la vigencia del CSD | Espera hasta 72 horas desde la emisión del certificado |
| 306 | El certificado no es de tipo CSD | Genera el CFDI con el CSD, no con la FIEL |
| 307 | El CFDI contiene un timbre previo | Finkok recupera el XML; espera unos segundos o usa `stamped()` |
| 308 | Certificado no expedido por el SAT | En DEMO usa el kit de pruebas |
| 401 | Fecha y hora de generación fuera de rango | Corrige `Fecha`; hay 72 horas y no se admiten fechas futuras |
| 402 | RFC del emisor no se encuentra en el régimen de contribuyentes | Revisa RFC y tipo de certificado |
| 603 | El CFDI no contiene un timbre previo | Primero timbra con `stamp()` |
| 701 | Cliente o RFC emisor suspendido | Habilita el RFC en «Clientes» |
| 702 | No ha registrado el RFC emisor | Regístralo en «Clientes» |
| 703 | Cuenta suspendida | Paga y registra el comprobante en «Cobranza» |
| 705 | XML estructura inválida | Revisa namespaces y `schemaLocation`, y que el XML vaya una sola vez en base64 |
| 707 | Timbre existente | Quita el nodo TFD del XML |
| 709 | SelloSat no pudo ser creado | Transitorio del balanceo de DEMO: reintenta |
| 712 | El atributo noCertificado no corresponde al certificado | Corrige `NoCertificado` |
| 718 | Timbres agotados | Asígnale timbres al RFC en «Clientes» |
| 719 | RFC del Emisor no corresponde al noCertificado | Carga los CSD en el panel (`sign_stamp`) |
| 720 | RFC del Emisor no tiene Certificado Activo | Carga los CSD del RFC |
| 738 | Errores con schemaLocations, namespaces y prefijos | Corrige el XML conforme al Anexo 20 |
| 740 | Error Firma de Manifiesto | Firma el manifiesto (RFM 2022, regla 2.7.2.1-II) |
| CFDI40102 | El resultado de la digestión debe ser igual al de la desencripción del sello | Revisa cadena original, espacios, UTF-8 y algoritmo SHA-256 |

```php
$code = ErrorCode::tryFrom((string) $incidencia->code);

$code?->description();
$code?->hint();
$code?->isTransient();            // 307 y 709
$code?->requiresManualAction();   // 304, 701, 702, 703, 718, 720, 740
$code?->isAlreadyStamped();       // 307 y 707
```

---

## 8. Errores de protocolo y de transporte

No son códigos de Finkok, sino condiciones detectadas por el bundle:

| Excepción | Causa | Qué hacer |
|---|---|---|
| `SoapFaultException` | SOAP Fault: error de esquema, operación inexistente, URL equivocada | Revisa la URL y el envelope con `previewStampRequest()` |
| `TransportException` | DNS, TLS, timeout, HTTP 4xx/5xx sin Fault | `isRetryable()` indica si conviene reintentar |
| `UnexpectedResponseException` | La respuesta no es un envelope SOAP | Suele indicar proxy, portal cautivo o URL equivocada |
| `ValidationException` | Validación local previa al envío | Corrige el XML o el argumento señalado |

---

## 9. Cómo codificar el CSD para cancelar

Finkok documenta el proceso de la llave:

```bash
# 1. La llave se convierte a PEM con su propia contraseña.
# 2. Ese PEM se cifra en DES3 con la contraseña del panel de Finkok.
# 3. El resultado se codifica en base64 y se envía como parámetro "key".
openssl rsa -in RFC.key.pem -des3 -out RFC.enc -passout pass:"su contraseña"
```

El bundle reproduce esos tres pasos en
`Estratos\FinkokBundle\Csd\PanelEncryptedCsdEncoder`, que es el codificador
registrado por defecto:

```php
// Credentials lo hace por ti: lee el .cer y el .key del perfil, los codifica
// una sola vez y memoriza el resultado.
$credentials->certificateBase64();
$credentials->privateKeyBase64();
```

Reglas que evitan los errores ya documentados:

- **Una sola codificación en base64**: la doble codificación produce el 704.
- **El certificado en base64 con sus encabezados PEM**: el error 711 aparece
  cuando «al momento de codificarlo a base64 no contiene los encabezados».
- **`Invalid Passphrase` / `Incorrect padding`**: la llave no se pudo descifrar.
  Revisa que la contraseña del CSD sea la correcta. Si cambiaste la contraseña
  del panel después de configurar el perfil, contacta a soporte: en cancelación
  el passphrase de la cuenta debe coincidir.
- La forma más simple de evitar todo esto es **no enviar CSD**: los parámetros
  `cer` y `key` son opcionales y el bundle los omite si el perfil no los tiene,
  en cuyo caso Finkok usa el certificado cargado en el panel.

Si necesitas el formato tradicional de OpenSSL en lugar del PKCS#8 que exporta
PHP, prepara el archivo con el comando de arriba y registra un
`CsdEncoderInterface` propio.

---

## 10. Comportamiento del ambiente DEMO

Sirve para integrar, no para pruebas de carga. Sus tiempos son particulares:

| Situación | Comportamiento |
|---|---|
| Cancelar justo después de timbrar | Hay que esperar de **1 a 5 minutos** a que el comprobante aparezca; si no, se recibe el 205 «UUID no existe» |
| Cancelable con aceptación | A partir de **$1,000 MXN** y tarda unos **30 minutos** en pasar de «cancelable sin aceptación» a «cancelable con aceptación». Se excluyen RFC genéricos y nómina sin relación |
| Aceptación o rechazo | Si el receptor no responde en los primeros **5 minutos**, la cancelación se resuelve sola y el estatus queda como «Plazo vencido» |
| No cancelable | Las facturas con relaciones duran unos **30 minutos** en ese estado; después la relación se rompe |
| Sustitución | Si se cancela una factura «no cancelable» con folio de sustitución, el estatus tarda en promedio **5 minutos** |

---

## Fuentes

- Documentación de Finkok: <https://wiki.finkok.com>
- Tipificación de errores de cancelación:
  <https://support.finkok.com/support/solutions/articles/31000156522>
- Validador de CFDI y de cadena original: <https://validador.finkok.com>
- Soporte: soporte@finkok.com