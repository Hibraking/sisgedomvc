<?php
/* =====================================================================
   SISGEDO MVC — Trasladar los archivos de sisgedov2 a la carpeta propia
   ---------------------------------------------------------------------
   Origen  (config 'ruta_archivos_antigua'):  docs adjs reg_doc · docs adjs docs_emitidos · images/perfiles
   Destino (config 'ruta_archivos'):          reg_doc · docs_emitidos · perfiles

   Uso (solo desde la consola / terminal de cPanel):
     php herramientas/trasladar_archivos.php            → SIMULA: cuenta y mide, no cambia nada
     php herramientas/trasladar_archivos.php --copiar   → copia los que faltan (sisgedov2 conserva los suyos)
     php herramientas/trasladar_archivos.php --mover    → mueve los que faltan (no ocupa el doble de espacio;
                                                          úselo cuando sisgedov2 ya NO se use)
   Se puede repetir: los archivos que ya están en el destino con el mismo tamaño se omiten
   y nunca se sobrescribe un archivo distinto. Mientras queden archivos sin trasladar, el
   sistema los sigue leyendo de la carpeta antigua (solo lectura).
   ===================================================================== */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo desde la consola.');
}
$_SERVER['HTTP_HOST'] = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'consola';
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\ArchivoService;

$modo = in_array('--mover', $argv, true) ? 'mover' : (in_array('--copiar', $argv, true) ? 'copiar' : 'simular');
if (!config('ruta_archivos_antigua')) {
    exit("No hay carpeta antigua configurada ('ruta_archivos_antigua' es null): nada que trasladar.\n");
}
$mb = function ($b) { return number_format($b / 1048576, 1) . ' MB'; };
echo "Modo: " . strtoupper($modo) . ($modo === 'simular' ? " (no se cambia nada)" : '') . "\n";
echo "Origen:  " . config('ruta_archivos_antigua') . "\nDestino: " . config('ruta_archivos') . "\n\n";

$totalErrores = 0;
foreach (array_keys(ArchivoService::CARPETAS) as $tipo) {
    $origen = ArchivoService::carpetaAntigua($tipo);
    $destino = ArchivoService::carpeta($tipo);
    $n = array('trasladar' => 0, 'bytes' => 0, 'ya' => 0, 'conflicto' => 0, 'error' => 0);
    if (!is_dir($origen)) {
        echo str_pad($tipo, 9) . " no existe la carpeta de origen ($origen)\n";
        continue;
    }
    foreach (new DirectoryIterator($origen) as $f) {
        if (!$f->isFile()) continue;
        $nombre = $f->getFilename();
        if ($nombre[0] === '.' || preg_match('/\.(php\d?|phtml|phar|htaccess)$/i', $nombre) || $nombre === 'index.php' || $nombre === 'error_log') {
            continue;                                // nunca se trasladan scripts ni archivos de control
        }
        $dest = $destino . '/' . $nombre;
        if (is_file($dest)) {
            if (filesize($dest) === $f->getSize()) $n['ya']++; else $n['conflicto']++;
            continue;                                // nunca se sobrescribe
        }
        $n['trasladar']++;
        $n['bytes'] += $f->getSize();
        if ($modo === 'simular') continue;
        $ok = $modo === 'mover' ? @rename($f->getPathname(), $dest) : @copy($f->getPathname(), $dest);
        if (!$ok && $modo === 'mover') {             // otro disco: copiar y luego borrar el original
            $ok = @copy($f->getPathname(), $dest) && filesize($dest) === $f->getSize() && @unlink($f->getPathname());
        }
        if (!$ok) {
            $n['error']++;
            fwrite(STDERR, "  No se pudo trasladar: $nombre\n");
        }
    }
    $totalErrores += $n['error'];
    printf("%-9s %s: %d (%s) · ya estaban: %d · con el mismo nombre pero distinto tamaño (no se tocan): %d%s\n",
        $tipo, $modo === 'simular' ? 'por trasladar' : ($modo === 'mover' ? 'movidos' : 'copiados'),
        $n['trasladar'] - $n['error'], $mb($n['bytes']), $n['ya'], $n['conflicto'], $n['error'] ? " · ERRORES: {$n['error']}" : '');
}
echo $totalErrores ? "\nTerminó con $totalErrores error(es).\n" : "\nTerminado.\n";
exit($totalErrores ? 1 : 0);
