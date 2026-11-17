<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\MesaPartesModel;
use App\Models\NotificacionModel;
use App\Services\ContextoService;

/**
 * GET notificaciones[?firma=...] — lo consulta assets/js/notificaciones.js cada 20 s.
 *   recepcion: pendientes de la oficina en sesión (+ los 5 más recientes si cambió la firma)
 *   mpv:       documentos de la Mesa de Partes Virtual (solo con acceso)
 * Solo lectura.
 */
class NotificacionController extends Controller
{
    public function consultar()
    {
        $verRecepcion = Auth::puede('vistas/v_recepcion_proveido.php');
        $verMpv = Auth::puede('vistas/v_mesa_partes_virtual.php');
        $ctx = ContextoService::actual();
        session_write_close();                      // no bloquear otras peticiones del usuario
        header('Cache-Control: no-store');

        $firmaCliente = $this->get('firma');
        $out = array('recepcion' => null, 'mpv' => null);
        try {
            $ofi = (int)$ctx['idoficina'];
            if ($ofi > 0 && $verRecepcion) {
                $p = NotificacionModel::firmaRecepcion($ofi);
                $total = 0;
                foreach ($p as $parte) $total += (int)$parte;          // "conteo.suma" -> conteo
                $firma = $ofi . '|' . implode('|', $p);
                $out['recepcion'] = array('oficina' => $ofi, 'pendientes' => $total, 'firma' => $firma,
                    'recientes' => $firma !== $firmaCliente ? NotificacionModel::recientes($ofi) : null);
            }
            if ($verMpv && MesaPartesModel::moduloActivo()) {
                $out['mpv'] = array('total' => MesaPartesModel::porAtender(), 'ultimo' => NotificacionModel::ultimoMpv());
            }
        } catch (\Exception $e) {
            error_log('notificaciones: ' . $e->getMessage());
            $this->json(array('error' => 'No se pudo consultar.'), 500);
        }
        $this->json($out);
    }
}
