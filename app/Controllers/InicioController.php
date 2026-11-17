<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\InicioModel;
use App\Models\OficinaModel;

/** Pantalla de Inicio (resumen de la oficina). */
class InicioController extends Controller
{
    public function index()
    {
        $ofi = Auth::idOficina();
        $h = (int)date('G');
        $meses = array(1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre');
        $dias = array('lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo');
        $actividad = InicioModel::actividadSemana($ofi);
        $max = 1;
        $total = 0;
        foreach ($actividad as $a) {
            $max = max($max, $a[1]);
            $total += $a[1];
        }
        $this->vista('inicio/index', array(
            'titulo' => 'Inicio',
            'saludo' => $h < 12 ? 'Buenos días' : ($h < 19 ? 'Buenas tardes' : 'Buenas noches'),
            'fecha' => $dias[(int)date('N') - 1] . ' ' . date('j') . ' de ' . $meses[(int)date('n')] . ' de ' . date('Y'),
            'oficina' => OficinaModel::nombre($ofi),
            'hoy' => InicioModel::hoy($ofi),
            'mes' => InicioModel::mes($ofi),
            'pendientes' => InicioModel::ultimosPendientes($ofi),
            'actividad' => $actividad,
            'maxActividad' => $max,
            'totalSemana' => $total,
        ));
    }
}
