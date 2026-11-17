<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\SupervisionModel;

/** Supervisión de oficinas (solo administrador): situación de todas las áreas y "Entrar" a cada bandeja. */
class SupervisionController extends Controller
{
    public function index()
    {
        $ofis = SupervisionModel::oficinas();
        $tot = array('pend' => 0, 'viejos' => 0, 'hoy' => 0, 'mes' => 0, 'con_pend' => 0);
        $pendPorOfi = array();
        foreach ($ofis as $o) {
            $tot['pend'] += $o['pend'];
            $tot['viejos'] += $o['viejos'];
            $tot['hoy'] += $o['hoy'];
            $tot['mes'] += $o['mes'];
            if ($o['pend'] > 0) $tot['con_pend']++;
            $pendPorOfi[(int)$o['id']] = (int)$o['pend'];
        }
        $this->vista('supervision/index', array(
            'titulo' => 'Supervisión',
            'cssExtra' => array('assets/css/supervision.css'),
            'ofis' => $ofis,
            'tot' => $tot,
            'mpv' => SupervisionModel::mpv(),
            'sinResp' => SupervisionModel::sinResponsable(),
            'variosCargos' => SupervisionModel::variosCargos(),
            'pendPorOfi' => $pendPorOfi,
            'tok' => Csrf::token(),
            'supHace' => function ($f) {
                if (!$f) return '—';
                $d = (int)floor((time() - strtotime($f)) / 86400);
                if ($d < 1) return 'hoy';
                if ($d < 60) return 'hace ' . $d . ($d === 1 ? ' día' : ' días');
                if ($d < 730) return 'hace ' . (int)round($d / 30) . ' meses';
                return 'hace ' . (int)round($d / 365) . ' años';
            },
        ));
    }
}
