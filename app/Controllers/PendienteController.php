<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Services\MenuService;

/**
 * Módulos que todavía no se migran: muestra en qué etapa se construyen y un
 * enlace a la pantalla equivalente del sistema actual (sisgedov2).
 */
class PendienteController extends Controller
{
    public function modulo(array $p)
    {
        $item = MenuService::itemPorRuta($p['ruta']);
        $titulo = $item ? $item[0] : (isset($p['titulo']) ? $p['titulo'] : 'Módulo');
        $this->vista('pendiente/index', array(
            'titulo' => $titulo,
            'icono' => $item ? $item[2] : (isset($p['icono']) ? $p['icono'] : 'fa-clock-o'),
            'etapa' => $item ? $item[4] : (isset($p['etapa']) ? $p['etapa'] : null),
            'antiguo' => $item ? $item[5] : (isset($p['antiguo']) ? $p['antiguo'] : ''),
        ));
    }
}
