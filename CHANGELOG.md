# Changelog

Todos los cambios relevantes de este proyecto se documentan en este archivo.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y el
proyecto se adhiere a [Versionado Semántico](https://semver.org/lang/es/).

## [Sin publicar]

## [1.0.0] - 2026-10-05

### Añadido

- Paquete `estratos/finkok-bundle` con el namespace `Estratos\FinkokBundle`.

- Servicio de timbrado con `stamp`, `quick_stamp`, `stamped`, `query_pending` y
  `sign_stamp`.
- Servicio de cancelación con `cancel`, `accept_reject`, `get_sat_status`,
  `get_pending`, `get_receipt` y `query_pending_cancellation`.
- Transporte SOAP document/literal sobre Symfony HttpClient: no requiere
  `ext-soap` ni en la instalación ni en tiempo de ejecución.
- Perfiles multi-emisor conmutables por nombre, con ambiente, RFC y CSD opcional
  para cancelar. Los aporta la aplicación mediante un servicio
  `CredentialsProviderInterface`: el bundle no admite credenciales en su propia
  configuración.
- DTOs inmutables para cada respuesta y catálogo `ErrorCode` con los 24 códigos
  de Finkok, su descripción y su pista de solución.
- Catálogo `CancellationStatusCode` con los 28 códigos de cancelación
  documentados (201-212 y `no_cancelable`, las validaciones de petición 300-314 y
  los errores propios 704, 708, 711, 798 y 799) y catálogo `AcceptRejectStatus`
  con los códigos 1000-1006 exclusivos del método `accept_reject`.
- Codificador CSD que reproduce el proceso documentado: la llave se cifra en DES3
  con la contraseña del panel y el certificado se envía en base64 con sus
  encabezados PEM.
- Excepciones tipadas: `TransportException`, `SoapFaultException`,
  `UnexpectedResponseException`, `ApiException`, `ValidationException`,
  `ConfigurationException` y `ProfileNotFoundException`.
- Validación previa del CFDI (vacío, mal formado, sin sello, que no es CFDI y
  límite de 1 MB) y detección unificada de credenciales inválidas en ambos
  servicios.
- Reintentos automáticos configurables, limitados a errores de transporte y
  códigos transitorios.
- Extensión de Symfony y compiler pass que reutilizan el cliente HTTP, el logger y
  el proveedor de credenciales de la aplicación cuando existen.
- 219 pruebas offline con `MockHttpClient` y 4 pruebas de contrato contra los
  servidores reales de Finkok.
- Documentación en español: `README.md` y `docs/errores.md`.

[Sin publicar]: https://github.com/estratos/finkok-bundle/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/estratos/finkok-bundle/releases/tag/v1.0.0
