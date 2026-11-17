# SISGEDO MVC

Reconstrucción del **SISGEDO v2** (Municipalidad Provincial de Huaytará) con
arquitectura **Modelo – Vista – Controlador**, por etapas, usando la **misma base
de datos** y reutilizando las consultas y reglas ya probadas de `sisgedov2`.

- Local: `http://localhost/sisgedomvc/`
- Base de datos: la misma de sisgedov2 (`config/database.php`).
- Sesión propia (`SISGEDOMVC`): no se mezcla con la de sisgedov2.
- Permisos: los mismos (`menu` / `accesos_usuario`).

## Estructura

```
public/            ← única carpeta visible en la web
  index.php        ← punto de entrada único (front controller)
  assets/          ← CSS, JS, imágenes y librerías (mismo diseño que sisgedov2)
app/
  bootstrap.php    ← arranque: clases, entorno, sesión y funciones de ayuda
  rutas.php        ← todas las rutas del sistema
  Core/            ← Router, Controller, View, Database, Session, Csrf, Auth, Url
  Controllers/     ← reciben la petición, validan y eligen la vista
  Services/        ← reglas del trámite (login, oficina de trabajo, menú…)
  Models/          ← solo SQL
  Views/           ← solo HTML (layouts/, auth/, inicio/…)
config/            ← app, base de datos, institución, menú
storage/logs/      ← registros (intentos de ingreso, errores en producción)
tests/             ← pruebas automáticas
```

Las carpetas `app/`, `config/`, `storage/` y `tests/` están bloqueadas desde la web.

### Cómo se agrega un módulo
1. **Modelo** en `app/Models/` con sus consultas.
2. **Servicio** en `app/Services/` si tiene reglas (validaciones, numeración, correos).
3. **Controlador** en `app/Controllers/`.
4. **Vista** en `app/Views/<modulo>/`.
5. En `app/rutas.php`, reemplazar la ruta "pendiente" por la del controlador,
   con su `permiso` (dirección del menú) — el enrutador exige sesión, permiso y
   token CSRF en los POST.

## Etapas

| Etapa | Contenido | Estado |
|---|---|---|
| 1 | Estructura MVC, conexión, inicio de sesión, menú lateral, selector de oficina del administrador, Inicio | ✅ |
| 2 | Recepción y Derivados (recepcionar, derivar, devolver, archivar desde la bandeja, adjuntos, exportaciones, notificaciones en vivo) | ✅ |
| 3 | Mesa de Partes Virtual (bandejas, observar, subsanar, correos) y páginas públicas | ✅ |
| 4 | Registro (mesa de partes presencial) y Emisión (numeración, firma) + cargos PDF | ✅ |
| 5 | Archivo y Supervisión | ✅ |
| 6 | Reportes, Mantenimiento, Configuración y Mi perfil | ✅ |

### Notas de la etapa 2
- Rutas: `recepcion` (pendientes y `?estado=Recepcionado`), `derivados`, `recepcion/accion` (POST),
  `adjunto?c=reg|emi&f=archivo` (lee las carpetas de adjuntos de sisgedov2) y `notificaciones`.
- El JavaScript de la bandeja (`assets/js/recepcion.js`) es el de sisgedov2: el POST usa las mismas claves.
- Controles nuevos respecto a sisgedov2: solo se recepciona, deriva o archiva lo que está en la oficina
  de la sesión; los archivadores salen de la sesión; un intento rechazado ya no consume número de registro.
- Aún se abren en sisgedov2: imprimir cargo (etapa 4), seguimiento/consultas (etapas 3 y 6) y
  "responder con documento" (etapa 4).

### Notas de la etapa 3
- Operador: `mesa-partes-virtual` (bandejas `?b=`), `mesa-partes-virtual/accion` (POST, mismas claves de sisgedov2).
- Públicas (sin sesión): `mesa-partes`, `consulta`, `subsanar`, `verificar`. Las direcciones antiguas
  (`mpvirtual.php`, `consulta_publico.php`, `subsanar.php`, `verificar.php`, `pdf/verificar.php`)
  redirigen (301): los enlaces de correos y los QR ya impresos siguen funcionando.
- Correo: `app/Services/CorreoService.php` (PHPMailer en `app/Libs/PHPMailer`), configuración en
  `config/correo.php`; la contraseña va en `config/correo_clave.php` (no se sube a Git). En local
  los correos van a Mailpit (http://localhost:8025).
- Calendario de días hábiles y horario: `CalendarioService`. Archivos, huella SHA-256 y código de
  verificación: `ArchivoService` (los adjuntos se siguen guardando en las carpetas de sisgedov2).
- Aún se abren en sisgedov2: cargos PDF (`pdf/cargo.php`, etapa 4).

### Notas de la etapa 4
- `registro` y `registro/accion` (también nuevo remitente) y `registro/reniec`; `emision` y `emision/accion`.
- Numeración de emitidos (`NumeracionService`): automática por oficina + tipo + año ({NUM}-{AÑO}-{SIGLAS}),
  manual sin repetir; el número se reserva después de validar.
- Firma Perú: `firma` (iniciar/estado con sesión; param/documento/subir para el firmador con token de un
  solo uso). Se activa sola al copiar `fwAuthorization.json` (credenciales de la PCM) en `config/firmaperu/`
  (bloqueada para la web y fuera de Git).
- Cargos PDF: `cargo?exp=` (con sesión) o `cargo?c=CÓDIGO` (ciudadano), `cargo-interno?exp=`. Dibujo en
  `app/Pdf/`. TCPDF se carga desde la carpeta de sisgedov2 (`PdfService`). Direcciones antiguas
  (`pdf/cargo.php`, `pdf/cargo_interno.php`, `tcpdf/examples/cargo_externo.php`) redirigen (301).
- Aún se abre en sisgedov2: el seguimiento interno (`reportes/consulta_interna.php`, etapa 6).

### Notas de la etapa 5
- `archivo` (`?bandeja=porarchivar|archivados`) y `archivo/accion`: archivar emitidos propios y tramitados
  (estos con `RecepcionService::archivar`, que también actualiza su estado) y crear archivadores.
  Controles nuevos: el archivador y el documento emitido deben ser de la oficina en sesión.
- `supervision` (solo administrador): indicadores de todas las oficinas; "Entrar" usa `oficina-trabajo`.
- Editar/eliminar archivadores y responsables: Mantenimiento (etapa 6).

### Notas de la etapa 6
- Catálogos simples (oficinas, documentos, tipos de trámite, acciones, estados, archivadores, menús):
  un solo `CatalogoController` configurado en `config/catalogos.php` (vista `catalogo/index`).
- `mantenimiento/remitentes|personal|responsables` y `configuracion/accesos` (`MantenimientoController`).
- `reportes/consulta-interna|estados|cuaderno-externo|cuaderno-interno` (+ `/pdf`) y `perfil`.
- Todas las opciones del menú están migradas: ya no se usa `PendienteController`.

## Archivos (independiente de sisgedov2)
- **Adjuntos y fotos**: `storage/archivos/` (`reg_doc/`, `docs_emitidos/`, `perfiles/`), protegida de la web
  (se entregan por `adjunto` y `foto-perfil` con sesión). Todo lo nuevo se guarda aquí.
- **TCPDF**: copia propia en `app/Libs/tcpdf` (configuración en `config/tcpdf.php`).
- **Archivos antiguos**: mientras no se trasladen, se leen (solo lectura) de la carpeta de sisgedov2
  indicada en `config/app.php` → `ruta_archivos_antigua`. sisgedomvc nunca escribe en ella.
- **Trasladarlos** (solo consola): `php herramientas/trasladar_archivos.php` (simula),
  `--copiar` (sisgedov2 conserva los suyos) o `--mover` (cuando sisgedov2 ya no se use; no ocupa el doble).
  Se puede repetir: omite lo ya trasladado y nunca sobrescribe ni traslada scripts.
- Al terminar el traslado, poner `'ruta_archivos_antigua' => null`.

## Paso a producción (resumen)
1. Subir sisgedomvc y crear `config/db_clave.php` y `config/correo_clave.php` (no van en Git); cuando la PCM
   entregue las credenciales de Firma Perú, copiar `fwAuthorization.json` en `config/firmaperu/`.
2. Probar con la carpeta antigua como respaldo (los adjuntos antiguos se siguen viendo).
3. Cuando sisgedov2 deje de usarse: `php herramientas/trasladar_archivos.php --mover`
   (si no hay consola: en el Administrador de archivos de cPanel mover el contenido de
   `docs adjs reg_doc`, `docs adjs docs_emitidos` e `images/perfiles` a `storage/archivos/reg_doc`,
   `docs_emitidos` y `perfiles`).
4. Poner `'ruta_archivos_antigua' => null` y comprobar que los adjuntos se ven.


## Producción (cuando esté completo)
- Copiar `config/db_clave.ejemplo.php` como `config/db_clave.php` con la clave de MySQL.
- La carpeta pública es `public/` (o dejar el `.htaccess` de la raíz, que la redirige).
