<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Url;
use App\Models\MesaPartesModel;

/** Arma el menú lateral según los permisos del usuario y la pantalla actual. */
class MenuService
{
    public static function definicion()
    {
        static $menu = null;
        if ($menu === null) {
            $menu = require SISGEDO_RAIZ . '/config/menu.php';
        }
        return $menu;
    }

    /** Busca una opción del menú por su ruta. */
    public static function itemPorRuta($ruta)
    {
        foreach (self::definicion() as $g) {
            foreach ($g['items'] as $it) {
                if ($it[1] === $ruta) return $it;
            }
        }
        return null;
    }

    /**
     * Grupos visibles para el usuario:
     * [['grupo','icono','activo'=>bool,'items'=>[['titulo','url','icono','activo','badge'], ...]], ...]
     */
    public static function grupos()
    {
        $actual = Url::rutaActual();
        $badgeMpv = null;
        $out = array();
        foreach (self::definicion() as $g) {
            $items = array();
            $grupoActivo = false;
            foreach ($g['items'] as $it) {
                if (!Auth::puede($it[3])) continue;
                $activo = ($actual === $it[1]);
                $grupoActivo = $grupoActivo || $activo;
                $badge = 0;
                if ($it[1] === 'mesa-partes-virtual') {
                    $badge = $badgeMpv === null ? ($badgeMpv = MesaPartesModel::porAtender()) : $badgeMpv;
                }
                $items[] = array('titulo' => $it[0], 'url' => url($it[1]), 'icono' => $it[2], 'activo' => $activo, 'badge' => $badge);
            }
            if ($items) {
                $out[] = array('grupo' => $g['grupo'], 'icono' => $g['icono'], 'activo' => $grupoActivo, 'items' => $items);
            }
        }
        return $out;
    }
}
