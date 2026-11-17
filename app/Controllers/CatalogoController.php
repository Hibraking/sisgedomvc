<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\CatalogoModel;
use App\Services\ContextoService;

/**
 * Catálogos de Mantenimiento y Configuración (config/catalogos.php).
 *   GET  <ruta>          listado (ej. mantenimiento/oficinas)
 *   POST <ruta>/accion   Codigo='' crea · Codigo=id modifica · Emilinar elimina (mismas claves que sisgedov2)
 */
class CatalogoController extends Controller
{
    public function index(array $p)
    {
        $def = CatalogoModel::definicion($p['clave']);
        $ofi = ContextoService::actual()['idoficina'];
        $kpis = array();
        foreach ($def['kpis'] as $k) {
            $kpis[] = array($k[0], number_format(CatalogoModel::kpi($k[1], $ofi)), $k[2], $k[3], $k[4], $k[5]);
        }
        $ncols = count($def['columnas']);
        $grid = '1.2fr' . str_repeat(' minmax(0, 1.6fr)', $ncols - 1) . ' 96px';
        $campos = array_map(function ($f) { return array('post' => $f[0], 'label' => $f[1], 'tipo' => $f[2], 'req' => $f[3]); }, $def['campos']);
        $this->vista('catalogo/index', array(
            'titulo' => $def['titulo'],
            'cssExtra' => array('assets/css/plantilla_catalogo.css'),
            'jsExtra' => array('assets/js/plantilla_catalogo.js'),
            'bodyData' => array(
                'endpoint' => url($def['ruta'] . '/accion'),
                'campos' => json_encode(array_map(function ($f) { return array('post' => $f['post'], 'req' => $f['req'], 'label' => $f['label']); }, $campos)),
                'ncols' => $ncols,
                'singular' => $def['singular'],
            ),
            'CAT' => array('titulo' => $def['titulo'], 'subtitulo' => $def['subtitulo'], 'icono' => $def['icono'], 'singular' => $def['singular'],
                           'columnas' => $def['columnas'], 'campos' => $campos, 'datos' => CatalogoModel::lista($def, $ofi)),
            'CAT_kpis' => $kpis,
            'CAT_grid' => $grid,
            'CAT_ncols' => $ncols,
        ));
    }

    public function accion(array $p)
    {
        $def = CatalogoModel::definicion($p['clave']);
        $ofi = ContextoService::actual()['idoficina'];
        header('Content-Type: text/plain; charset=utf-8');
        $codigo = (string)$this->post('Codigo');
        $porOficina = !empty($def['oficina']);

        if (isset($_POST['Emilinar'])) {
            if ($porOficina && !CatalogoModel::deOficina($def, $codigo, $ofi)) {
                exit('ERROR_VALIDACION: Solo puede eliminar registros de su oficina.');
            }
            try {
                CatalogoModel::eliminar($def, $codigo);
                exit('OK');
            } catch (\Exception $e) {
                error_log('Catálogo eliminar ' . $def['tabla'] . ': ' . $e->getMessage());
                exit('ERROR_EN_USO: No se puede eliminar porque está siendo usado en otros registros del sistema.');
            }
        }
        if (!isset($_POST['Codigo'])) {
            http_response_code(400);
            exit('ERROR_VALIDACION: Acción no reconocida.');
        }

        // Valores a guardar (columnas solo desde la configuración)
        $valores = array();
        foreach ($def['campos'] as $f) {
            $v = trim((string)$this->post($f[0]));
            if ($f[3] && $v === '') exit('ERROR_VALIDACION: Complete el campo: ' . $f[1]);
            if (!mb_check_encoding($v, 'UTF-8')) exit('ERROR_VALIDACION: El campo ' . $f[1] . ' tiene caracteres no válidos.');
            if ($f[2] === 'number') {
                if ($v !== '' && !is_numeric($v)) exit('ERROR_VALIDACION: ' . $f[1] . ' debe ser un número.');
                $v = $v === '' ? 0 : (int)$v;
            }
            if (isset($def['vacio_nulo']) && in_array($f[4], $def['vacio_nulo'], true) && $v === '') $v = null;
            $valores[$f[4]] = $v;
        }
        foreach (isset($def['extra']) ? $def['extra'] : array() as $col => $post) {
            $valores[$col] = trim((string)$this->post($post));
        }
        if (!empty($def['unico'])) {
            $campoUnico = null;
            foreach ($def['campos'] as $f) if ($f[4] === $def['unico']) $campoUnico = $f;
            if ($valores[$def['unico']] !== '' && CatalogoModel::duplicado($def, $valores[$def['unico']], (int)$codigo)) {
                exit('ERROR_VALIDACION: Ya existe ' . $def['singular'] . ' con ese ' . mb_strtolower($campoUnico[1], 'UTF-8') . '.');
            }
        }
        try {
            if ($codigo === '') {
                if ($porOficina) $valores['idoficina'] = $ofi;
                CatalogoModel::crear($def, $valores);
            } else {
                if ($porOficina && !CatalogoModel::deOficina($def, $codigo, $ofi)) {
                    exit('ERROR_VALIDACION: Solo puede modificar registros de su oficina.');
                }
                CatalogoModel::modificar($def, $codigo, $valores);
            }
        } catch (\Exception $e) {
            error_log('Catálogo guardar ' . $def['tabla'] . ': ' . $e->getMessage());
            exit('ERROR_VALIDACION: No se pudo guardar. Revise los datos e intente nuevamente.');
        }
        exit('OK');
    }
}
