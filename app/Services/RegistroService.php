<?php
namespace App\Services;

use App\Core\Auth;
use App\Models\RegistroModel;

/**
 * Registro presencial — reglas de sisgedov2/clases/reg_docs.php:
 *  - un registro por oficina destino, todos con el mismo N° de expediente;
 *  - solo se modifica/elimina lo que nadie recepcionó ni tiene movimientos;
 *  - quien registra sale de la sesión.
 * Devuelven true/valor o false con el motivo en $this->error.
 */
class RegistroService
{
    public $error = '';

    private function datos(array $p)
    {
        return array(
            'iddocumento' => (int)(isset($p['Documento']) ? $p['Documento'] : ''), 'idremitente' => (int)(isset($p['Remitente']) ? $p['Remitente'] : ''), 'idtipo_tramite' => (int)(isset($p['TipoTram']) ? $p['TipoTram'] : ''),
            'numero_docu' => trim((string)(isset($p['NumDoc']) ? $p['NumDoc'] : '')), 'folios' => (int)(isset($p['Folios']) ? $p['Folios'] : ''), 'asunto' => trim((string)(isset($p['Asunto']) ? $p['Asunto'] : '')),
            'parasu' => (string)(isset($p['Parasu']) ? $p['Parasu'] : ''), 'observacion' => (string)(isset($p['observacion']) ? $p['observacion'] : ''), 'procedencia' => (string)(isset($p['Procedencia']) ? $p['Procedencia'] : ''),
            'idpersonal' => Auth::id(),
        );
    }

    /** @return string|false  N° de expediente creado */
    public function registrar(array $p, $archivo)
    {
        $d = $this->datos($p);
        $cadena = trim((string)(isset($p['lista_oficinas']) ? $p['lista_oficinas'] : ''));
        $errores = array();
        if ($d['iddocumento'] < 1) $errores[] = 'Seleccione el tipo de documento.';
        if ($d['idremitente'] < 1) $errores[] = 'Seleccione o registre el remitente.';
        if ($d['idtipo_tramite'] < 1) $errores[] = 'Seleccione el tipo de trámite.';
        if ($d['asunto'] === '') $errores[] = 'Ingrese el asunto.';
        if ($d['folios'] < 1) $errores[] = 'Ingrese un número de folios válido.';
        $oficinas = array_values(array_filter(array_map('intval', explode(',', $cadena))));
        if (!$oficinas) $errores[] = 'Seleccione al menos una oficina destino.';
        if (!$this->textoValido($d)) $errores[] = 'Hay texto con caracteres no válidos; vuelva a escribirlo.';
        if ($errores) {
            $this->error = implode(' ', $errores);
            return false;
        }
        $d['adjunto'] = null;
        if ($archivo && !empty($archivo['tmp_name']) && is_uploaded_file($archivo['tmp_name'])) {
            $g = ArchivoService::guardar($archivo, 'REGISTRO', 'PRESENCIAL');
            if (!$g['ok']) {
                $this->error = $g['msg'];
                return false;
            }
            $d['adjunto'] = $g['nombre'];
        }
        $db = \App\Core\Database::pdo();
        try {
            $db->beginTransaction();               // todos los destinos o ninguno
            $d['nro_exp'] = NumeracionService::nuevoExpediente();
            foreach ($oficinas as $ofi) {
                $d['idoficina'] = $ofi;
                RegistroModel::insertar($d);
            }
            $db->commit();
        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($d['adjunto']) @unlink(ArchivoService::carpeta('REGISTRO') . '/' . $d['adjunto']);
            error_log('registrar: ' . $e->getMessage());
            $this->error = 'No se pudo registrar el documento. Intente nuevamente.';
            return false;
        }
        ArchivoService::cvd('EXPEDIENTE', $d['nro_exp']);   // código de verificación del cargo (QR)
        return $d['nro_exp'];
    }

    /** Modificar (también se usa para "Derivar a una oficina"). */
    public function modificar($codigo, array $p, $archivo)
    {
        if (!RegistroModel::editable($codigo)) {
            $this->error = 'El documento ya fue recepcionado o tiene movimientos; no puede modificarse.';
            return false;
        }
        $d = $this->datos($p);
        if (!$this->textoValido($d)) {
            $this->error = 'Hay texto con caracteres no válidos; vuelva a escribirlo.';
            return false;
        }
        $d['idoficina'] = (int)(isset($p['lista_oficinas']) ? $p['lista_oficinas'] : '') ?: null;
        $adjunto = null;
        if ($archivo && !empty($archivo['tmp_name']) && is_uploaded_file($archivo['tmp_name'])) {
            $g = ArchivoService::guardar($archivo, 'REGISTRO', 'PRESENCIAL');
            if (!$g['ok']) {
                $this->error = $g['msg'];
                return false;
            }
            $adjunto = $g['nombre'];
        }
        try {
            RegistroModel::modificar($codigo, $d);
            if ($adjunto !== null) RegistroModel::actualizarAdjunto($codigo, $adjunto);
        } catch (\Exception $e) {
            error_log('modificar registro: ' . $e->getMessage());
            $this->error = 'No se pudo guardar el cambio. Intente nuevamente.';
            return false;
        }
        return true;
    }

    /** Los textos deben venir en UTF-8 válido (si no, la base de datos los rechaza). */
    private function textoValido(array $d)
    {
        foreach ($d as $v) {
            if (is_string($v) && !mb_check_encoding($v, 'UTF-8')) return false;
        }
        return true;
    }

    public function eliminar($codigo)
    {
        if (!RegistroModel::editable($codigo)) {
            $this->error = 'El documento ya fue recepcionado o tiene movimientos; no puede eliminarse.';
            return false;
        }
        try {
            if (RegistroModel::eliminar($codigo)) return true;
            $this->error = 'No se encontró el registro (puede que ya se haya eliminado).';
        } catch (\Exception $e) {
            error_log('eliminar registro ' . $codigo . ': ' . $e->getMessage());
            $this->error = 'No se pudo eliminar en la base de datos: ' . $e->getMessage();
        }
        return false;
    }

    /** Nuevo remitente: reutiliza uno con el mismo correo o el mismo nombre completo. */
    public function remitente($apellidos, $nombres, $direccion, $email, $telefono)
    {
        if ($email !== '' && ($id = RegistroModel::remitentePorEmail($email)) !== null) return $id;
        if (($id = RegistroModel::remitentePorNombre($apellidos, $nombres)) > 0) return $id;
        return RegistroModel::crearRemitente($apellidos, $nombres, $direccion, $email, $telefono);
    }

    /**
     * Nombres por DNI (misma fuente pública que usaba sisgedov2: JNE).
     * Devuelve [dni, apellido paterno, apellido materno, nombres].
     */
    public static function consultarDni($dni)
    {
        if (!preg_match('/^\d{8}$/', (string)$dni)) {      // antes el DNI iba sin validar a la dirección
            return array($dni, '', '', '');
        }
        $ctx = stream_context_create(array('http' => array('timeout' => 10), 'https' => array('timeout' => 10)));
        $txt = @file_get_contents('http://aplicaciones007.jne.gob.pe/srop_publico/Consulta/Afiliado/GetNombresCiudadano?DNI=' . $dni, false, $ctx);
        $partes = explode('|', trim(html_entity_decode(strip_tags((string)$txt), ENT_QUOTES, 'UTF-8')));
        return array($dni, isset($partes[0]) ? $partes[0] : '', isset($partes[1]) ? $partes[1] : '', isset($partes[2]) ? $partes[2] : '');
    }
}
