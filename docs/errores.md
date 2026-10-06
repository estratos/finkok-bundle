# Códigos de error de Finkok

Referencia de los códigos que devuelven los Web Services de timbrado y
cancelación, con la reacción recomendada y el equivalente en el bundle.

> **Regla de oro documentada por Finkok**: un comprobante puede haberse timbrado
> correctamente y aun así regresar incidencias. La integración debe decidir con
> `CodEstatus` y con la presencia del UUID, **nunca** con la sola presencia de
> incidencias.

---

## 1. Incidencias del Web Service de timbrado

Viajan dentro de `<Incidencias><Incidencia>` en una respuesta SOAP correcta.
Se leen con `StampReceipt::getIncidences()` y se tipifican con
`Estratos\FinkokBundle\Model\ErrorCode`.

| Código | `CodigoError` | Significado | Cómo resolverlo | Enum |
|---|---|---|---|---|
| 300 | El usuario o contraseña son inválidos | Credenciales incorrectas o URL de un ambiente distinto al de las credenciales. | Verifica usuario y contraseña y que la URL corresponda al ambiente. `$receipt->isCredentialError()` | `ErrorCode::InvalidCredentials` |
| 301 | XML mal formado | Faltan campos obligatorios o hay atributos vacíos. | Valida en `https://validador.finkok.com`. El bundle lo detecta antes de enviar. | `ErrorCode::MalformedXml` |
| 303 | Sello no corresponde a emisor | El sello se generó con una `.key` que no corresponde al emisor del XML. | Usa el par CSD correcto del emisor declarado. | `ErrorCode::SealDoesNotMatchIssuer` |
| 304 | Certificado revocado o caduco | El CSD ya no está activo ante el SAT. | Consulta la lista de certificados del SAT y sustitúyelo. | `ErrorCode::RevokedOrExpiredCertificate` |
| 305 | La fecha de emisión no está dentro de la vigencia del CSD | El certificado se emitió hace minutos u horas y la lista LCO no está actualizada. | Espera hasta 72 horas desde la emisión del certificado. | `ErrorCode::IssueDateOutsideCertificateValidity` |
| 306 | El certificado no es de tipo CSD | Se está usando el certificado de la FIEL. | Genera el CFDI con el CSD (la FIEL solo aplica al servicio gratuito del SAT). | `ErrorCode::NotACsdCertificate` |
| 307 | El CFDI contiene un timbre previo | El comprobante ya está timbrado. | Finkok recupera el XML automáticamente; espera unos segundos o usa `stamped()`. `$receipt->isPreviouslyStamped()` | `ErrorCode::AlreadyStamped` |
| 308 | Certificado no expedido por el SAT | En DEMO se usan RFC y certificados reales. | Usa el kit de pruebas de Finkok. | `ErrorCode::CertificateNotIssuedBySat` |
| 401 | Fecha y hora de generación fuera de rango | Se intenta timbrar fuera de las 72 horas o con fecha futura. | Corrige el atributo `Fecha` del comprobante. | `ErrorCode::IssueDateOutOfRange` |
| 402 | RFC del emisor no se encuentra en el régimen de contribuyentes | RFC o tipo de certificado incorrectos; certificado nuevo sin propagar. | Verifica el RFC, usa CSD y espera 72 horas si el certificado es nuevo. | `ErrorCode::IssuerRfcNotRegisteredInTaxRegime` |
| 603 | El CFDI no contiene un timbre previo | Se consultó con `stamped()` un comprobante sin timbrar. | Primero timbra con `stamp()`. | `ErrorCode::MissingPreviousStamp` |
| 701 | Cliente o RFC emisor suspendido | El RFC está suspendido en la sección «Clientes» del panel. | Habilítalo en el panel del ambiente correspondiente. | `ErrorCode::IssuerSuspended` |
| 702 | No ha registrado el RFC emisor bajo la cuenta de Finkok | El RFC no existe en el panel. | Regístralo en «Clientes». | `ErrorCode::IssuerNotRegisteredInFinkok` |
| 703 | Cuenta suspendida | Existe un adeudo. | Paga y registra el comprobante en «Cobranza». | `ErrorCode::AccountSuspended` |
| 705 | XML estructura inválida | Cabeceras, `schemaLocation`, prefijos o codificación base64 incorrectos; XML doblemente codificado; se usó la URL de CFDI para una retención. | Revisa namespaces y `xsi:schemaLocation`, envía el XML una sola vez en base64 y, para retenciones, usa `retentions.wsdl`. | `ErrorCode::InvalidXmlStructure` |
| 707 | Timbre existente | El XML ya trae el nodo `tfd:TimbreFiscalDigital`. | Quita el nodo TFD o genera el XML sin él. | `ErrorCode::ExistingStamp` |
| 709 | SelloSat no pudo ser creado | Incidencia transitoria del balanceo de DEMO. | Reintenta en unos momentos. | `ErrorCode::SatSealCouldNotBeCreated` |
| 712 | El atributo noCertificado no corresponde al certificado | El número de serie no coincide con el certificado. | Corrige `NoCertificado`. | `ErrorCode::CertificateNumberMismatch` |
| 718 | Timbres agotados | El RFC emisor tiene 0 timbres asignados. | Asígnale timbres en «Clientes». | `ErrorCode::StampsExhausted` |
| 719 | RFC del Emisor no corresponde al noCertificado | Se usó `sign_stamp` sin cargar los certificados en el panel. | Carga los CSD en el panel. | `ErrorCode::IssuerRfcDoesNotMatchCertificate` |
| 720 | RFC del Emisor no tiene Certificado Activo | `sign_stamp` sin CSD cargados para ese RFC. | Carga los CSD en «Clientes» o usa el método `edit` del WS de registro. | `ErrorCode::IssuerHasNoActiveCertificate` |
| 738 | Errores con schemaLocations, namespaces y prefijos | El `schemaLocation` o los namespaces están incompletos o mal escritos. | Corrige el XML conforme al Anexo 20. | `ErrorCode::SchemaLocationOrNamespaceError` |
| 740 | Error Firma de Manifiesto | El RFC emisor no firmó el manifiesto de conformidad (RFM 2022, regla 2.7.2.1-II). | Firma el manifiesto. | `ErrorCode::ManifestSignatureError` |
| CFDI40102 | El resultado de la digestión debe ser igual al resultado de la desencripción del sello | Cadena original o sello mal generados: orden de atributos, espacios, saltos de línea, UTF-8, algoritmo SHA-256 o llave que no corresponde. | Compara tu cadena original con la del validador de Finkok y verifica el par CSD. | `ErrorCode::CfdiDigestMismatch` |

### Clasificación programática

```php
$code = ErrorCode::tryFrom((string) $incidencia->code);

$code?->description();            // descripción oficial
$code?->hint();                   // qué hacer
$code?->isTransient();            // 307 y 709: reintentar sirve
$code?->requiresManualAction();   // 304, 701, 702, 703, 718, 720, 740: panel o SAT
$code?->isAlreadyStamped();       // 307 y 707
```

---

## 2. `CodEstatus` del método `cancel`

Estos códigos **no** son incidencias ni el `EstatusUUID` del SAT: son la
respuesta propia del servicio de cancelación de Finkok y llegan en `CodEstatus`.
Se tipifican con `Estratos\FinkokBundle\Model\CancellationStatusCode`.

| Código | Significado | Cómo resolverlo | Enum |
|---|---|---|---|
| 201 | Petición de cancelación realizada exitosamente | Confirma la cancelación definitiva con `get_sat_status` | `RequestAccepted` |
| 202 | Petición de cancelación realizada previamente | No reintentes; consulta el estatus | `RequestAlreadySent` |
| 203 | No corresponde el RFC del Emisor y de quien solicita la cancelación | `taxpayer_id` debe ser el RFC del Emisor del CFDI | `IssuerMismatch` |
| 205 | UUID no encontrado | Verifica el UUID; espera si el CFDI se acaba de timbrar | `UuidNotFound` |
| 304 | Certificado revocado o caduco | Usa un CSD vigente (no tiene que ser el mismo con el que se timbró) | `RevokedOrExpiredCertificate` |
| 704 | El certificado o la llave se enviaron doblemente codificados en base64 | Codifica una sola vez (el bundle lo hace así por defecto) | `DoubleBase64Encoding` |
| 798 | Ya existe una solicitud previa | Consulta el estatus; si sigue vigente, espera 72 horas | `AlreadyRequested` |
| 799 | Se excedieron las 5 peticiones de cancelación | Ya no es posible cancelar por este medio: contacta a soporte | `TooManyAttempts` |

### Errores de texto (sin código numérico)

| `CodEstatus` / `error` | Significado | Cómo resolverlo |
|---|---|---|
| `Invalid Username or Password` | Credenciales inválidas. **El WS de cancelación lo reporta así**, en inglés y sin nodo `<Incidencias>`; el de timbrado usa la incidencia 300. | `$receipt->isCredentialError()` |
| `Invalid Passphrase` | La llave privada no pudo descifrarse, el certificado o la llave se codificaron dos veces, o la contraseña del panel cambió después de cifrar la llave. | Revisa la codificación (`CsdEncoderInterface`) o contacta a soporte si cambiaste la contraseña del panel |
| `Already en BufferCancellation` | La solicitud anterior quedó en el buffer de cancelaciones de Finkok. | Envía `store_pending: false` si no quieres que se almacene |

---

## 3. `EstatusUUID` por folio (respuesta del SAT)

Cada `Folio` de `CancelaCFDResult` trae el código con el que respondió el SAT.

| Código | Significado | Método del DTO |
|---|---|---|
| 201 | Cancelación exitosa (sin aceptación del receptor) | `CancellationFolio::isCancelled()` |
| 202 | Cancelación exitosa (con aceptación del receptor) | `CancellationFolio::isCancelled()` |
| 203 | El CFDI no es cancelable | `CancellationFolio::isNotCancellable()` |
| 204 | Solicitud enviada correctamente; en proceso con aceptación | `CancellationFolio::isInProcess()` |
| 205 | Solicitud enviada correctamente; en proceso sin aceptación | `CancellationFolio::isInProcess()` |

---

## 4. `get_sat_status`

| Código | Significado | Cómo resolverlo |
|---|---|---|
| `S - Comprobante obtenido satisfactoriamente.` | El SAT localizó el comprobante | `$status->details?->isFound()` |
| `N 601 La expresión impresa proporcionada no es válida.` | Los parámetros no coinciden con el CFDI; casi siempre el `total` sin los decimales exactos | Usa `getStatusOf($cfdi)` para tomar los datos del XML |
| `N 602 Comprobante no encontrado` | UUID erróneo, el SAT aún no tiene registro del CFDI, o los parámetros no pertenecen a ese UUID | Verifica el UUID y espera si se acaba de timbrar |
| `no_cancelable` | El CFDI tiene CFDI relacionados con estatus vigente | Consulta los UUID relacionados antes de cancelar |

Campos útiles del detalle (`SatStatusDetails`):

| Campo | Valores | Método del DTO |
|---|---|---|
| `CodigoEstatus` | `S - …` / `N …` | `isFound()` |
| `Estado` | `Vigente` / `Cancelado` | `isActive()` / `isCancelled()` |
| `EsCancelable` | `Cancelable con aceptación` / `Cancelable sin aceptación` / `No cancelable` | `isCancellable()`, `requiresReceiverAcceptance()`, `canCancelWithoutAcceptance()` |
| `EstatusCancelacion` | `Cancelado`, `En proceso`, `Solicitud rechazada`, … | `cancellationStatus` |
| `ValidacionEFOS` | `100`, `101`, `200`, `201` | `isListedAsEfos()` (200/201) |

---

## 5. Errores de protocolo y de transporte

Estos no son códigos de Finkok, sino condiciones detectadas por el bundle:

| Excepción | Causa | Qué hacer |
|---|---|---|
| `SoapFaultException` | SOAP Fault: error de esquema, operación inexistente, URL equivocada | Revisa la URL del servicio y el envelope (`previewStampRequest()`) |
| `TransportException` | DNS, TLS, timeout, HTTP 4xx/5xx sin Fault | `isRetryable()` indica si conviene reintentar |
| `UnexpectedResponseException` | La respuesta no es un envelope SOAP | Suele indicar proxy, portal cautivo o URL equivocada (por ejemplo la de retenciones) |
| `ValidationException` | Validación local previa al envío | Corrige el XML o el argumento señalado |

---

## Fuentes

- Documentación de Finkok: <https://wiki.finkok.com>
- Tipificación de errores de cancelación:
  <https://support.finkok.com/support/solutions/articles/31000156522>
- Validador de CFDI y de cadena original: <https://validador.finkok.com>
- Soporte: soporte@finkok.com
