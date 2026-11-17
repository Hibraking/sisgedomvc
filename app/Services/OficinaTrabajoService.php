<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\OficinaModel;

/**
 * "Trabajar en nombre de" (solo administrador), igual que sisgedov2:
 * cambia idoficina / idresponsable de la sesión (lo que usan todos los
 * módulos) y guarda los originales para volver a "Mi oficina".
 * Lo que haga en otra oficina queda con un cargo propio "en nombre de".
 */
class OficinaTrabajoService
{
    private function original()
    {
        $o = Session::get('admin_original');
        if (is_array($o)) return $o;
        return array('idoficina' => Auth::idOficina(), 'idresponsable' => Auth::idResponsable());
    }

    /** ['en_otra' => bool, 'idoficina' => int, 'nombre' => str, 'propia_nombre' => str] */
    public function actual()
    {
        $orig = $this->original();
        $actual = Auth::idOficina();
        $nombres = OficinaModel::nombres(array($actual, $orig['idoficina']));
        return array(
            'en_otra' => (int)Session::get('ver_oficina', 0) > 0 && (int)Session::get('ver_oficina') !== (int)$orig['idoficina'],
            'idoficina' => $actual,
            'nombre' => isset($nombres[$actual]) ? $nombres[$actual] : '',
            'propia_nombre' => isset($nombres[(int)$orig['idoficina']]) ? $nombres[(int)$orig['idoficina']] : '',
        );
    }

    /** @return true|string  true o el motivo del error */
    public function cambiar($destino)
    {
        if (!Auth::esAdmin()) {
            return 'Solo el administrador puede cambiar de oficina.';
        }
        if (!is_array(Session::get('admin_original'))) {
            Session::set('admin_original', $this->original());
        }
        $orig = Session::get('admin_original');
        if ($destino === 'propia' || (int)$destino === (int)$orig['idoficina']) {
            Session::set('idoficina', (int)$orig['idoficina']);
            Session::set('idresponsable', (int)$orig['idresponsable']);
            Session::quitar('ver_oficina');
            Session::quitar('admin_original');
            return true;
        }
        $id = (int)$destino;
        if (!OficinaModel::existe($id)) {
            return 'La oficina no existe.';
        }
        Session::set('idresponsable', OficinaModel::responsableEnNombre($id));
        Session::set('idoficina', $id);
        Session::set('ver_oficina', $id);
        return true;
    }
}
