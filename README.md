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
| 4 | Registro (mesa de partes presencial) y Emisión (numeración, firma) + cargos PDF | Pendiente |
| 5 | Archivo y Supervisión | Pendiente |
| 6 | Reportes, Mantenimiento, Configuración y Mi perfil | Pendiente |

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

Mientras un módulo no está migrado, su opción del menú muestra en qué etapa se
construye y un enlace a la misma pantalla en sisgedov2.

## Producción (cuando esté completo)
- Copiar `config/db_clave.ejemplo.php` como `config/db_clave.php` con la clave de MySQL.
- La carpeta pública es `public/` (o dejar el `.htaccess` de la raíz, que la redirige).
