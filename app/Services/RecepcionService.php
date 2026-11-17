<?php
namespace App\Services;

use App\Core\Database;
use App\Models\RecepcionModel;

/**
 * Reglas del trámite en Recepción — mismas de sisgedov2 (clases/recepcion_proveido.php):
 *  - recepcionar (primer movimiento de un registro o emitido), sin duplicados
 *  - recibir una derivación pendiente (solo si sigue pendiente)
 *  - derivar / devolver lo recibido (las dos operaciones juntas)
 *  - archivar (una sola vez)
 * Cada método devuelve true / id, o false con el motivo en $this->error.
 */
class RecepcionService
{
    public $error = '';

    /** Recepción inicial. Devuelve el id del movimiento creado o 0. */
    public function recepcionar($idregdocs, $iddocs_emitidos, $idaccion, $folio, $detalles)
    {
        $this->error = '';
        $ctx = ContextoService::actual();
        if ((int)$idregdocs > 0) {
            $idregdocs = (int)$idregdocs;
            $iddocs_emitidos = null;
        } else {
            $idregdocs = null;
            $iddocs_emitidos = (int)$iddocs_emitidos > 0 ? (int)$iddocs_emitidos : null;
        }
        if ($idregdocs === null && $iddocs_emitidos === null) {
            $this->error = 'No se identificó el documento. Actualice la página.';
            return 0;
        }
        $db = Database::pdo();
        try {
            $db->beginTransaction();
            if ($idregdocs !== null) {
                $lock = $db->prepare("SELECT idoficina FROM registro_documentos WHERE idregistro_documentos = ? FOR UPDATE");
                $lock->execute(array($idregdocs));
                $ya = $db->prepare("SELECT COUNT(*) FROM movimientos WHERE idregdocs = ? AND idmovimientoant IS NULL");
                $ya->execute(array($idregdocs));
            } else {
                $lock = $db->prepare("SELECT idoficina FROM docs_emitidos WHERE iddocs_emitidos = ? FOR UPDATE");
                $lock->execute(array($iddocs_emitidos));
                $ya = $db->prepare("SELECT COUNT(*) FROM movimientos WHERE iddocs_emitidos = ? AND idmovimientoant IS NULL");
                $ya->execute(array($iddocs_emitidos));
            }
            $destino = $lock->fetchColumn();
            if ($destino === false) {
                $db->rollBack();
                $this->error = 'El documento ya no existe. Actualice la página.';
                return 0;
            }
            if ((int)$destino !== (int)$ctx['idoficina']) {
                // Nuevo control: solo la oficina a la que fue dirigido puede recepcionarlo
                $db->rollBack();
                $this->error = 'Este documento no está dirigido a su oficina. Actualice la página.';
                return 0;
            }
            if ((int)$ya->fetchColumn() > 0) {
                $db->rollBack();
                $this->error = 'Este documento ya fue recepcionado. Actualice la página.';
                return 0;
            }
            // El número se toma recién aquí (dentro de la transacción): un intento
            // rechazado ya no consume un número de registro.
            $numExp = NumeracionService::registroInterno($ctx['idoficina']);
            $ins = $db->prepare("INSERT INTO movimientos(idregdocs,iddocs_emitidos,idacciondocs,idoficina_derivar,idresponsable,num_exp,detalles,folio)
                                 VALUES(?,?,?,?,?,?,?,?)");
            $ins->execute(array($idregdocs, $iddocs_emitidos, (int)$idaccion, $ctx['idoficina'], $ctx['idresponsable'] ?: null, $numExp, $detalles, (int)$folio));
            $id = (int)$db->lastInsertId();
            $db->commit();
            return $id;
        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('recepcionar: ' . $e->getMessage());
            $this->error = 'No se pudo recepcionar el documento. Intente nuevamente.';
            return 0;
        }
    }

    /** Recibir una derivación pendiente (solo si sigue con estado 0 y es de la oficina). */
    public function recibirDerivado($idmovimiento, $folio, $detalles)
    {
        $this->error = '';
        $ctx = ContextoService::actual();
        $db = Database::pdo();
        try {
            // Solo si sigue PENDIENTE y es de la oficina; el número se toma después
            // de comprobarlo (un intento rechazado no consume número).
            $db->beginTransaction();
            $lock = $db->prepare("SELECT idMovimientos FROM movimientos WHERE idMovimientos = ? AND estado = 0 AND idoficina_derivar = ? FOR UPDATE");
            $lock->execute(array((int)$idmovimiento, $ctx['idoficina']));
            if ($lock->fetchColumn() === false) {
                $db->rollBack();
                $this->error = 'Este documento ya fue recepcionado (o ya no está pendiente). Actualice la página.';
                return false;
            }
            $numExp = NumeracionService::registroInterno($ctx['idoficina']);
            $db->prepare("UPDATE movimientos
                             SET idestadodocs=1,idresponsable=?,num_exp=?,detalles=?,fecha_hora=now(),estado=1,folio=?
                           WHERE idMovimientos=? AND estado=0")
               ->execute(array($ctx['idresponsable'] ?: null, $numExp, $detalles, (int)$folio, (int)$idmovimiento));
            $db->commit();
            return true;
        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('recibirDerivado: ' . $e->getMessage());
            $this->error = 'No se pudo recepcionar el documento. Intente nuevamente.';
            return false;
        }
    }

    /** ¿Ya hay un envío pendiente (no recibido) de este movimiento a esa oficina? */
    public function derivacionPendiente($codigo, $idoficinaDestino)
    {
        try {
            return (int)Database::valor("SELECT COUNT(*) FROM movimientos WHERE idmovimientoant = ? AND idoficina_derivar = ? AND estado = 0",
                array((int)$codigo, (int)$idoficinaDestino)) > 0;
        } catch (\Exception $e) {
            error_log('derivacionPendiente: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Derivar o devolver lo que tiene la oficina: crea el nuevo movimiento y
     * marca el actual como atendido, en una sola transacción.
     */
    public function derivar($codigo, $idregdocs, $iddocs_emitidos, $idaccion, $idestado, $idoficinaDestino, $detalles, $nuevoEstado)
    {
        $this->error = '';
        $ctx = ContextoService::actual();
        if ((int)$idregdocs > 0) {
            $idregdocs = (int)$idregdocs;
            $iddocs_emitidos = null;
        } else {
            $idregdocs = null;
            $iddocs_emitidos = (int)$iddocs_emitidos > 0 ? (int)$iddocs_emitidos : null;
        }
        if ((int)$idoficinaDestino <= 0) {
            $this->error = 'Seleccione la oficina destino.';
            return false;
        }
        $db = Database::pdo();
        try {
            $db->beginTransaction();
            $lock = $db->prepare("SELECT idoficina_derivar, idregdocs, iddocs_emitidos FROM movimientos WHERE idMovimientos = ? FOR UPDATE");
            $lock->execute(array((int)$codigo));
            $mov = $lock->fetch(\PDO::FETCH_ASSOC);
            if (!$mov) {
                $db->rollBack();
                $this->error = 'No se encontró el movimiento del documento. Actualice la página.';
                return false;
            }
            if ((int)$mov['idoficina_derivar'] !== (int)$ctx['idoficina']) {
                // Nuevo control: solo se deriva lo que está en la oficina propia
                $db->rollBack();
                $this->error = 'Este documento no está en su oficina. Actualice la página.';
                return false;
            }
            if ((int)$mov['idregdocs'] !== (int)$idregdocs || (int)$mov['iddocs_emitidos'] !== (int)$iddocs_emitidos) {
                $db->rollBack();
                $this->error = 'Los datos del documento no coinciden. Actualice la página.';
                return false;
            }
            if ($this->derivacionPendiente($codigo, $idoficinaDestino)) {
                $db->rollBack();
                $this->error = 'Este documento ya fue enviado a esa oficina y aún no lo recibe. Actualice la página.';
                return false;
            }
            $db->prepare("INSERT INTO movimientos(idregdocs,iddocs_emitidos,idacciondocs,idestadodocs,idoficina_derivar,detalle_derivado,idmovimientoant)
                          VALUES(?,?,?,?,?,?,?)")
               ->execute(array($idregdocs, $iddocs_emitidos, $idaccion ?: null, $idestado ?: null, (int)$idoficinaDestino, $detalles, (int)$codigo));
            $db->prepare("UPDATE movimientos SET estado=1,idestadodocs=?,iddocrespuesta=NULL WHERE idMovimientos=?")
               ->execute(array($nuevoEstado ?: null, (int)$codigo));
            $db->commit();
            return true;
        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('derivar: ' . $e->getMessage());
            $this->error = 'No se pudo completar la derivación. Intente nuevamente.';
            return false;
        }
    }

    /** Archivar un documento de la oficina en uno de sus archivadores (una sola vez). */
    public function archivar($idarchivador, $idmovimiento, $detalles, $idestado)
    {
        $this->error = '';
        $ctx = ContextoService::actual();
        if (!RecepcionModel::archivadorDeOficina($idarchivador, $ctx['idoficina'])) {
            $this->error = 'Seleccione un archivador de su oficina.';
            return false;
        }
        $db = Database::pdo();
        try {
            $db->beginTransaction();
            $lock = $db->prepare("SELECT idoficina_derivar FROM movimientos WHERE idMovimientos = ? FOR UPDATE");
            $lock->execute(array((int)$idmovimiento));
            $ofi = $lock->fetchColumn();
            if ($ofi === false) {
                $db->rollBack();
                $this->error = 'No se encontró el documento. Actualice la página.';
                return false;
            }
            if ((int)$ofi !== (int)$ctx['idoficina']) {
                $db->rollBack();
                $this->error = 'Este documento no está en su oficina. Actualice la página.';
                return false;
            }
            $ya = $db->prepare("SELECT COUNT(*) FROM docs_archivados WHERE idmovimientos = ?");
            $ya->execute(array((int)$idmovimiento));
            if ((int)$ya->fetchColumn() > 0) {
                $db->rollBack();
                $this->error = 'Este documento ya está archivado.';
                return false;
            }
            $db->prepare("INSERT INTO docs_archivados(iddocs_archivados,idarchivador,idmovimientos,detalles) VALUES(null,?,?,?)")
               ->execute(array((int)$idarchivador, (int)$idmovimiento, $detalles));
            $db->prepare("UPDATE movimientos SET idestadodocs=? WHERE idMovimientos=?")
               ->execute(array($idestado ?: null, (int)$idmovimiento));
            $db->commit();
            return true;
        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('archivar: ' . $e->getMessage());
            $this->error = 'No se pudo archivar el documento. Intente nuevamente.';
            return false;
        }
    }
}
