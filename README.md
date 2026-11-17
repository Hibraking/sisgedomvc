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
| 2 | Recepción y Derivados (recepcionar, derivar, responder, devolver, archivar desde la bandeja, notificaciones en vivo) | Pendiente |
| 3 | Mesa de Partes Virtual (bandejas, observar, subsanar, correos) y páginas públicas | Pendiente |
| 4 | Registro (mesa de partes presencial) y Emisión (numeración, firma) + cargos PDF | Pendiente |
| 5 | Archivo y Supervisión | Pendiente |
| 6 | Reportes, Mantenimiento, Configuración y Mi perfil | Pendiente |

Mientras un módulo no está migrado, su opción del menú muestra en qué etapa se
construye y un enlace a la misma pantalla en sisgedov2.

## Producción (cuando esté completo)
- Copiar `config/db_clave.ejemplo.php` como `config/db_clave.php` con la clave de MySQL.
- La carpeta pública es `public/` (o dejar el `.htaccess` de la raíz, que la redirige).
