<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use PDO;

/**
 * Mi perfil (mismo diseño y reglas que sisgedov2: vistas/v_profile.php y clases/profile.php).
 *   GET  perfil          datos, oficinas, indicadores y último acceso
 *   POST perfil/accion   actualizar_perfil: dirección, email, teléfono, contraseña (con la actual) y foto
 */
class PerfilController extends Controller
{
    public function index()
    {
        $id = Auth::id();
        $esAdmin = Auth::esAdmin();
        $perfil = null;
        $oficinasResp = array();
        $nRoles = $nOficinas = $nDocs = $pctPermisos = 0;
        $miembroDesde = '—';
        try {
            $r = Database::filas("SELECT apellidos, nombres, COALESCE(direccion,''), COALESCE(email,''), COALESCE(telefono,''), COALESCE(usuario,''), COALESCE(imagen,'') FROM personal WHERE idpersonal=?", array($id), PDO::FETCH_NUM);
            $perfil = $r ? $r[0] : null;
            $oficinasResp = Database::filas("SELECT o.idoficinas, o.nombre, COALESCE(o.descripcion,''), r.estado FROM responsables r INNER JOIN oficinas o ON r.idoficina=o.idoficinas WHERE r.idpersonal=? ORDER BY r.estado DESC, o.nombre ASC", array($id), PDO::FETCH_NUM);
            $nOficinas = count($oficinasResp);
            $activas = 0;
            foreach ($oficinasResp as $o) if ((int)$o[3] === 1) $activas++;
            $nRoles = max(1, ($esAdmin ? 1 : 0) + ($activas > 0 ? 1 : 0));
            $nDocs = (int)Database::valor("SELECT COUNT(*) FROM registro_documentos WHERE idpersonal=?", array($id))
                + (int)Database::valor("SELECT COUNT(*) FROM movimientos m INNER JOIN responsables r ON m.idresponsable=r.idresponsables WHERE r.idpersonal=?", array($id));
            $totalMenus = (int)Database::valor("SELECT COUNT(*) FROM menu WHERE direccion IS NOT NULL AND direccion <> '#'");
            if ($esAdmin) {
                $pctPermisos = 100;
            } elseif ($totalMenus > 0) {
                $pctPermisos = (int)round((int)Database::valor("SELECT COUNT(*) FROM accesos_usuario a INNER JOIN menu m ON a.idmenu=m.idmenu WHERE a.idusuario=? AND a.access=1 AND m.direccion IS NOT NULL AND m.direccion <> '#'", array($id)) * 100 / $totalMenus);
            }
            $fechas = array_filter(array(
                Database::valor("SELECT MIN(fecha_registro) FROM registro_documentos WHERE idpersonal=?", array($id)),
                Database::valor("SELECT MIN(m.fecha_hora) FROM movimientos m INNER JOIN responsables r ON m.idresponsable=r.idresponsables WHERE r.idpersonal=?", array($id)),
            ));
            if ($fechas) {
                sort($fechas);
                $miembroDesde = date('d/m/Y', strtotime($fechas[0]));
            }
        } catch (\Exception $e) {
            error_log('perfil: ' . $e->getMessage());
        }
        // Último acceso real (el inicio de sesión guarda el acceso anterior)
        $formatear = function ($ua) {
            $ts = strtotime((string)$ua);
            if ($ts === false) return 'No registrado aún';
            return date('Y-m-d', $ts) === date('Y-m-d') ? 'Hoy, ' . date('h:i a', $ts) : date('d/m/Y h:i a', $ts);
        };
        $ultimoAcceso = 'No registrado aún';
        if (array_key_exists('ultimo_acceso_anterior', $_SESSION)) {
            $ua = $_SESSION['ultimo_acceso_anterior'];
            $ultimoAcceso = $ua ? $formatear($ua) : 'Este es tu primer acceso registrado';
        } else {
            try {
                $ua = Database::valor("SELECT ultimo_acceso FROM personal WHERE idpersonal=?", array($id));
                if ($ua) $ultimoAcceso = $formatear($ua);
            } catch (\Exception $e) {
                // columna aún no creada
            }
        }
        $fotoUrl = '';
        if ($perfil && $perfil[6] !== '' && is_file(\App\Services\ArchivoService::ruta('PERFIL', $perfil[6]))) {
            $fotoUrl = url('foto-perfil') . '?v=' . rawurlencode($perfil[6]);
        }
        $this->vista('perfil/index', array(
            'titulo' => 'Perfil de Usuario',
            'claseBody' => 'v-profile',
            'cssExtra' => array('assets/css/v_vistas.css'),
            'jsExtra' => array('assets/js/perfil.js'),
            'bodyData' => array('endpoint' => url('perfil/accion')),
            'perfil' => $perfil, 'oficinasResp' => $oficinasResp, 'nRoles' => $nRoles, 'nOficinas' => $nOficinas, 'nDocs' => $nDocs,
            'pctPermisos' => $pctPermisos, 'miembroDesde' => $miembroDesde, 'ultimoAcceso' => $ultimoAcceso, 'fotoUrl' => $fotoUrl,
            'idoficinaSes' => Auth::idOficina(),
            'nombreCompleto' => $perfil ? trim($perfil[1] . ' ' . $perfil[0]) : Auth::alias(),
            'rolPrincipal' => $esAdmin ? 'Administrador del Sistema' : 'Usuario del Sistema',
        ));
    }

    public function accion()
    {
        header('Content-Type: text/plain; charset=utf-8');
        if (!isset($_POST['actualizar_perfil'])) exit('ERROR_VALIDACION: Acción no reconocida.');
        $id = Auth::id();
        $direccion = trim((string)$this->post('direccion'));
        $email = trim((string)$this->post('email'));
        $telefono = trim((string)$this->post('telefono'));
        $claveNueva = isset($_POST['clave_nueva']) ? (string)$_POST['clave_nueva'] : '';
        $claveActual = isset($_POST['clave_actual']) ? (string)$_POST['clave_actual'] : '';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) exit('ERROR_VALIDACION: El email no tiene un formato válido.');
        try {
            if ($claveNueva !== '') {
                if (strlen($claveNueva) < 6) exit('ERROR_VALIDACION: La nueva contraseña debe tener al menos 6 caracteres.');
                $guardada = Database::valor("SELECT clave FROM personal WHERE idpersonal=?", array($id));
                $ok = false;
                if ($guardada !== false && $guardada !== null) {
                    $ok = preg_match('/^\$2y\$/', $guardada) ? password_verify($claveActual, $guardada)
                        : (hash_equals((string)$guardada, $claveActual) && $claveActual !== '');
                }
                if (!$ok) exit('ERROR_VALIDACION: La contraseña actual no es correcta.');
                Database::consulta("UPDATE personal SET clave=? WHERE idpersonal=?", array(password_hash($claveNueva, PASSWORD_DEFAULT), $id));
            }
            // Foto: imagen real, máx. 3 MB, nombre aleatorio; se borra la anterior (solo si está en la carpeta propia)
            if (isset($_FILES['foto']) && is_uploaded_file($_FILES['foto']['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'), true)) exit('ERROR_VALIDACION: La foto debe ser JPG, PNG, GIF o WEBP.');
                if ($_FILES['foto']['size'] <= 0 || $_FILES['foto']['size'] > 3 * 1024 * 1024) exit('ERROR_VALIDACION: La foto no debe superar los 3 MB.');
                if (@getimagesize($_FILES['foto']['tmp_name']) === false) exit('ERROR_VALIDACION: El archivo no es una imagen válida.');
                $dir = \App\Services\ArchivoService::carpeta('PERFIL') . '/';
                $nombre = 'perfil_' . $id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                if (!move_uploaded_file($_FILES['foto']['tmp_name'], $dir . $nombre)) exit('ERROR_ARCHIVO: No se pudo guardar la foto.');
                $anterior = Database::valor("SELECT imagen FROM personal WHERE idpersonal=?", array($id));
                if ($anterior && strpos($anterior, 'perfil_') === 0 && is_file($dir . $anterior)) @unlink($dir . $anterior);
                Database::consulta("UPDATE personal SET imagen=? WHERE idpersonal=?", array($nombre, $id));
                Session::set('foto_perfil', $nombre);
            }
            Database::consulta("UPDATE personal SET direccion=?, email=?, telefono=? WHERE idpersonal=?", array($direccion, $email, $telefono, $id));
            exit('OK');
        } catch (\Exception $e) {
            error_log('actualizar_perfil: ' . $e->getMessage());
            exit('ERROR_BD: no se pudo actualizar el perfil.');
        }
    }
}
