<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\MantenimientoModel as MM;

/**
 * Mantenimiento con pantalla propia (mismo diseño y claves POST que sisgedov2):
 *   mantenimiento/remitentes   (+ /accion: draw (tabla) · Codigo (crear/editar) · Emilinar)
 *   mantenimiento/personal     (+ /accion: Codigo · Emilinar)
 *   mantenimiento/responsables (+ /accion: Codigo · Emilinar)
 *   configuracion/accesos      (+ /accion: otorgar · Emilinar)
 */
class MantenimientoController extends Controller
{
    private function pantalla($vista, $titulo, $clase, $js, $ruta, array $datos, array $bodyExtra = array())
    {
        $this->vista($vista, $datos + array(
            'titulo' => $titulo,
            'claseBody' => $clase,
            'cssExtra' => array('assets/css/v_vistas.css'),
            'jsExtra' => array('assets/js/' . $js),
            'bodyData' => array('endpoint' => url($ruta . '/accion')) + $bodyExtra,
        ));
    }

    private function texto($s)
    {
        header('Content-Type: text/plain; charset=utf-8');
        exit($s);
    }

    private function eliminar($tabla, $pk)
    {
        try {
            MM::eliminar($tabla, $pk, (int)$this->post('Codigo', 0));
            $this->texto('OK');
        } catch (\Exception $e) {
            error_log("eliminar $tabla: " . $e->getMessage());
            $this->texto('ERROR_EN_USO: No se puede eliminar porque está siendo usado en otros registros del sistema.');
        }
    }

    /** Textos del POST (recortados y en UTF-8 válido). */
    private function campos(array $claves)
    {
        $d = array();
        foreach ($claves as $c) {
            $v = trim((string)$this->post($c));
            if (!mb_check_encoding($v, 'UTF-8')) $this->texto('ERROR_VALIDACION: Hay texto con caracteres no válidos.');
            $d[$c] = $v;
        }
        return $d;
    }

    /* ---------------- Remitentes ---------------- */

    public function remitentes()
    {
        $k = MM::kpisRemitentes();
        $this->pantalla('mantenimiento/remitentes', 'Remitentes', 'v-remitentes', 'remitentes.js', 'mantenimiento/remitentes',
            array('totalRemitentes' => $k['total'], 'conEmail' => $k['conEmail'], 'nuevosMes' => $k['nuevosMes']),
            array('urlRegistro' => url('registro')));
    }

    public function remitentesAccion()
    {
        if (isset($_POST['draw'])) {
            $orden = isset($_POST['order'][0]) ? $_POST['order'][0] : array();
            $buscar = isset($_POST['search']['value']) ? trim((string)$_POST['search']['value']) : '';
            $this->json(MM::tablaRemitentes((int)$this->post('draw', 0), (int)$this->post('start', 0), (int)$this->post('length', 25), $buscar,
                isset($orden['column']) ? (int)$orden['column'] : 2, isset($orden['dir']) && strtolower($orden['dir']) === 'desc' ? 'DESC' : 'ASC'));
        }
        if (isset($_POST['Emilinar'])) $this->eliminar('remitente', 'idremitente');
        if (isset($_POST['Codigo'])) {
            $d = $this->campos(array('Codigo', 'Apellidos', 'Nombres', 'Direccion', 'Email', 'Telefono'));
            if ($d['Apellidos'] === '' || $d['Nombres'] === '') $this->texto('ERROR_VALIDACION: Ingrese apellidos y nombres.');
            if ($d['Email'] !== '' && !filter_var($d['Email'], FILTER_VALIDATE_EMAIL)) $this->texto('ERROR_VALIDACION: El email no tiene un formato válido.');
            try {
                $this->texto((string)MM::guardarRemitente($d['Codigo'], $d['Apellidos'], $d['Nombres'], $d['Direccion'], $d['Email'], $d['Telefono']));
            } catch (\Exception $e) {
                error_log('remitente: ' . $e->getMessage());
                $this->texto('ERROR_VALIDACION: No se pudo guardar el remitente.');
            }
        }
        $this->texto('ERROR_VALIDACION: Acción no reconocida.');
    }

    /* ---------------- Personal ---------------- */

    public function personal()
    {
        $p = MM::personal();
        $this->pantalla('mantenimiento/personal', 'Personal', 'v-personal', 'personal.js', 'mantenimiento/personal',
            $p + array('first' => count($p['filas']) ? $p['filas'][0] : null));
    }

    public function personalAccion()
    {
        if (isset($_POST['Emilinar'])) $this->eliminar('personal', 'idpersonal');
        if (isset($_POST['Codigo'])) {
            $d = $this->campos(array('Codigo', 'Apellidos', 'Nombres', 'Direccion', 'Email', 'Telefono', 'nusu'));
            $clave = (string)$this->post('Clave');
            if ($d['Apellidos'] === '' || $d['Nombres'] === '') $this->texto('ERROR_VALIDACION: Ingrese apellidos y nombres.');
            if ($d['Codigo'] === '' && $d['nusu'] !== '' && $clave === '') $this->texto('ERROR_VALIDACION: Ingrese una contraseña para el nuevo usuario.');
            if ($clave !== '' && strlen($clave) < 6) $this->texto('ERROR_VALIDACION: La contraseña debe tener al menos 6 caracteres.');
            if ($d['nusu'] !== '' && MM::usuarioOcupado($d['nusu'], (int)$d['Codigo'])) $this->texto('ERROR_VALIDACION: Ese nombre de usuario ya lo usa otra persona.');
            try {
                MM::guardarPersonal($d['Codigo'], array('apellidos' => $d['Apellidos'], 'nombres' => $d['Nombres'], 'direccion' => $d['Direccion'],
                    'email' => $d['Email'], 'telefono' => $d['Telefono'], 'usuario' => $d['nusu']), $clave);
                $this->texto('OK');
            } catch (\Exception $e) {
                error_log('personal: ' . $e->getMessage());
                $this->texto('ERROR_VALIDACION: No se pudo guardar.');
            }
        }
        $this->texto('ERROR_VALIDACION: Acción no reconocida.');
    }

    /* ---------------- Responsables ---------------- */

    public function responsables()
    {
        $r = MM::responsables();
        $activos = 0;
        foreach ($r['filas'] as $f) if ((int)$f[3] === 1) $activos++;
        $this->pantalla('mantenimiento/responsables', 'Responsables', 'v-responsables', 'responsables.js', 'mantenimiento/responsables',
            $r + array('activos' => $activos, 'first' => count($r['filas']) ? $r['filas'][0] : null));
    }

    public function responsablesAccion()
    {
        if (isset($_POST['Emilinar'])) $this->eliminar('responsables', 'idresponsables');
        if (isset($_POST['Codigo'])) {
            $per = (int)$this->post('Personal', 0);
            $ofi = (int)$this->post('Oficina', 0);
            if ($per < 1 || $ofi < 1) $this->texto('ERROR_VALIDACION: Seleccione la persona y la oficina.');
            try {
                MM::guardarResponsable((string)$this->post('Codigo'), $per, $ofi, $this->post('Estado') === 'on' ? 1 : 0);
                $this->texto('OK');
            } catch (\Exception $e) {
                error_log('responsable: ' . $e->getMessage());
                $this->texto('ERROR_VALIDACION: No se pudo guardar.');
            }
        }
        $this->texto('ERROR_VALIDACION: Acción no reconocida.');
    }

    /* ---------------- Accesos del personal ---------------- */

    public function accesos()
    {
        $usuarioSel = (int)$this->get('u', '0');
        $menus = $usuarioSel > 0 ? MM::menusDeUsuario($usuarioSel) : array();
        $concedidos = 0;
        foreach ($menus as $m) if (!empty($m[4])) $concedidos++;
        $this->pantalla('configuracion/accesos', 'Accesos del Personal', 'v-accesos-personal', 'accesos.js', 'configuracion/accesos',
            array('usuarioSel' => $usuarioSel, 'usuarios' => MM::usuariosConCuenta(), 'menus' => $menus, 'concedidos' => $concedidos),
            array('usuario' => $usuarioSel, 'urlBase' => url('configuracion/accesos')));
    }

    public function accesosAccion()
    {
        if (isset($_POST['otorgar'])) {
            MM::concederAcceso((int)$this->post('Idmenu', 0), (int)$this->post('Idusuario', 0));
            $this->texto('OK');
        }
        if (isset($_POST['Emilinar'])) {
            MM::revocarAcceso((int)$this->post('Codigo', 0));
            $this->texto('OK');
        }
        $this->texto('ERROR_VALIDACION: Acción no reconocida.');
    }
}
