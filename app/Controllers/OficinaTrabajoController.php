<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Services\OficinaTrabajoService;

/** Selector "Oficina de trabajo / Trabajar en nombre de" del administrador. */
class OficinaTrabajoController extends Controller
{
    public function cambiar()
    {
        $destino = (string)$this->post('ver_oficina', 'propia');
        $volver = (string)$this->post('volver', '');
        if ($destino === 'todas') {
            $this->redirigir('supervision');
        }
        $r = (new OficinaTrabajoService())->cambiar($destino);
        if ($r !== true) {
            Session::flash('aviso', $r);
        }
        // Solo se vuelve a rutas internas del sistema
        $this->redirigir(preg_match('~^[a-z0-9/_-]*$~i', $volver) ? $volver : '');
    }
}
