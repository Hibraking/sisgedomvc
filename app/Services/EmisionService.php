<?php
namespace App\Services;

use App\Core\Auth;
use App\Models\EmisionModel;

/**
 * Emisión — reglas de sisgedov2/clases/docs_emitidos.php (MGD):
 *  - numeración automática por oficina + tipo + año (manual solo excepcional, sin repetir);
 *  - el número se reserva recién después de validar (no se "queman" correlativos);
 *  - un envío (fila) por oficina destino, cada uno con su código de verificación;
 *  - solo se modifica/elimina lo que nadie recepcionó ni tiene movimientos;
 *  - el emisor sale de la sesión.
 */
class EmisionService
{
    public $error = '';

    private function oficinaEmisora()
    {
        return EmisionModel::oficinaResponsable(Auth::idResponsable());
    }

    public function previsualizar($iddocumento)
    {
        $ofi = $this->oficinaEmisora();
        if ($ofi < 1 || (int)$iddocumento < 1) {
            return array('ok' => false, 'numero' => '', 'mensaje' => 'Seleccione el tipo de documento.');
        }
        try {
            return NumeracionService::previsualizarEmitido($ofi, (int)$iddocumento);
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return array('ok' => false, 'numero' => '', 'mensaje' => 'Numeración automática no disponible (¿se ejecutó la migración 001?).');
        }
    }

    /** @return string|false  "OK|<número>|<CVD del primer envío>" */
    public function emitir(array $p, $archivo)
    {
        $g = function ($k) use ($p) { return isset($p[$k]) ? (string)$p[$k] : ''; };
        $iddocumento = (int)$g('Documento');
        $asunto = trim($g('Asunto'));
        $detalles = $g('Detalles');
        $numero = trim($g('NumDoc'));
        $automatico = $g('Numeracion') !== 'manual';
        $ofi = $this->oficinaEmisora();
        if ($iddocumento < 1) return $this->falla('Seleccione el tipo de documento.');
        if ($asunto === '') return $this->falla('Ingrese el asunto.');
        if (!$automatico && $numero === '') return $this->falla('Ingrese el número del documento.');
        if ($automatico && NumeracionService::siglasOficina($ofi) === '') {
            return $this->falla('Su oficina no tiene siglas configuradas para la numeración automática. Pida al administrador registrarlas en Oficinas, o use numeración manual.');
        }
        if (!$automatico && NumeracionService::numeroEmitidoExiste($ofi, $iddocumento, $numero)) {
            return $this->falla('Ya existe un documento de este tipo con el número ' . $numero . ' en su oficina este año.');
        }
        $adjunto = null;
        if ($archivo && !empty($archivo['tmp_name']) && is_uploaded_file($archivo['tmp_name'])) {
            $r = ArchivoService::guardar($archivo, 'EMITIDO', 'INTERNO');
            if (!$r['ok']) {
                $this->error = $r['msg'];
                return false;
            }
            $adjunto = $r['nombre'];
        }
        if ($automatico) {
            $numero = NumeracionService::reservarEmitido($ofi, $iddocumento);
        } else {
            NumeracionService::registrarNumeroManual($ofi, $iddocumento, $numero);
        }
        $cadena = $g('listaoficinas');
        $destinos = $cadena === '' ? array(null) : array_map('intval', explode(',', $cadena));
        $primerId = null;
        try {
            foreach ($destinos as $idofi) {
                $id = EmisionModel::insertar($iddocumento, Auth::idResponsable() ?: null, $idofi ?: null, $asunto, $detalles, $numero, $adjunto);
                if ($primerId === null) $primerId = $id;
                ArchivoService::cvd('EMITIDO', $id);       // cada envío tiene su propio código
            }
        } catch (\Exception $e) {
            error_log('emitir: ' . $e->getMessage());
            $this->error = 'No se pudo registrar el documento.';
            return false;
        }
        $cvd = $primerId ? ArchivoService::cvd('EMITIDO', $primerId) : null;
        return 'OK|' . $numero . '|' . ($cvd ? ArchivoService::formatoCvd($cvd) : '');
    }

    public function modificar($codigo, array $p)
    {
        if (!EmisionModel::editable($codigo)) return $this->falla('El documento ya fue recepcionado o tiene movimientos; no puede modificarse.');
        $g = function ($k) use ($p) { return isset($p[$k]) ? (string)$p[$k] : ''; };
        $numero = trim($g('NumDoc'));
        if ($numero === '') return $this->falla('Ingrese el número del documento.');
        $iddocumento = (int)$g('Documento');
        NumeracionService::registrarNumeroManual($this->oficinaEmisora(), $iddocumento, $numero);
        // Igual que sisgedov2: queda el último destino de la lista; vacío = sin destino
        $lista = array_filter(array_map('trim', explode(',', $g('listaoficinas'))), 'strlen');
        $oficina = $lista ? (int)end($lista) : null;
        EmisionModel::modificar($codigo, $iddocumento, Auth::idResponsable() ?: null, $oficina ?: null, $g('Asunto'), $g('Detalles'), $numero);
        return true;
    }

    public function eliminar($codigo)
    {
        if (!EmisionModel::editable($codigo)) return $this->falla('El documento ya fue recepcionado o tiene movimientos; no puede eliminarse.');
        EmisionModel::eliminar($codigo);
        return true;
    }

    private function falla($msg)
    {
        $this->error = $msg;
        return false;
    }
}
