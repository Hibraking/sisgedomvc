<?php
namespace App\Services;

use App\Core\Database;
use App\Models\ArchivoModel;
use App\Models\RecepcionModel;

/**
 * Archivo de documentos — reglas de sisgedov2/clases/archivar_docs.php y archivador.php:
 *  - un documento se archiva una sola vez (bloqueo FOR UPDATE);
 *  - los tramitados se archivan con RecepcionService::archivar (también actualiza su estado).
 * Controles nuevos: el archivador debe ser de la oficina en sesión y el emitido, de esa oficina.
 */
class ArchivadorService
{
    public $error = '';

    /** Archiva un documento EMITIDO por la propia oficina. */
    public function archivarEmitido($idarchivador, $iddocsEmitidos, $detalles)
    {
        $ofi = ContextoService::actual()['idoficina'];
        if (!RecepcionModel::archivadorDeOficina($idarchivador, $ofi)) return $this->falla('Seleccione un archivador de su oficina.');
        $emisora = ArchivoModel::oficinaEmisora($iddocsEmitidos);
        if ($emisora === null) return $this->falla('El documento ya no existe. Actualice la página.');
        if ($emisora !== (int)$ofi) return $this->falla('Este documento no fue emitido por su oficina.');
        $db = Database::pdo();
        try {
            $db->beginTransaction();
            $lock = $db->prepare("SELECT iddocs_emitidos FROM docs_emitidos WHERE iddocs_emitidos = ? FOR UPDATE");
            $lock->execute(array((int)$iddocsEmitidos));
            $ya = $db->prepare("SELECT COUNT(*) FROM docs_archivados WHERE iddocs_emitidos = ?");
            $ya->execute(array((int)$iddocsEmitidos));
            if ((int)$ya->fetchColumn() > 0) {
                $db->rollBack();
                return $this->falla('Este documento ya está archivado.');
            }
            $db->prepare("INSERT INTO docs_archivados(idarchivador,iddocs_emitidos,idmovimientos,detalles) VALUES(?,?,NULL,?)")
               ->execute(array((int)$idarchivador, (int)$iddocsEmitidos, $detalles));
            $db->commit();
            return true;
        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('archivarEmitido: ' . $e->getMessage());
            return $this->falla('No se pudo archivar el documento. Intente nuevamente.');
        }
    }

    /** Nuevo archivador de la oficina en sesión. */
    public function crearArchivador($nombre, $detalles, $anio)
    {
        $nombre = trim((string)$nombre);
        if ($nombre === '') return $this->falla('Ingrese el nombre del archivador.');
        if ((int)$anio < 2000 || (int)$anio > (int)date('Y') + 1) return $this->falla('Ingrese un año válido.');
        try {
            ArchivoModel::crearArchivador(ContextoService::actual()['idoficina'], $nombre, (string)$detalles, (int)$anio);
            return true;
        } catch (\Exception $e) {
            error_log('crearArchivador: ' . $e->getMessage());
            return $this->falla('No se pudo crear el archivador.');
        }
    }

    private function falla($msg)
    {
        $this->error = $msg;
        return false;
    }
}
