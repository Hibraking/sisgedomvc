<?php
namespace App\Pdf;

use App\Core\Url;
use App\Services\ArchivoService;

/**
 * Cargo de recepción de documento EXTERNO (mesa de partes) — mismo diseño que
 * sisgedov2/pdf/cargo.php (propuesta C), en una sola hoja A4 siempre:
 *   1) encabezado con logo y N° de expediente;
 *   2) TALÓN DEL CIUDADANO (se corta y se entrega) + QR;
 *   3) EJEMPLAR DE LA ENTIDAD · HOJA DE RUTA + QR;
 *   4) RECORRIDO: casillas para el sello de cada área (12, o 9 con SISGEDO_CARGO_CASILLAS = 9).
 * Requiere TCPDF cargado (PdfService::cargar()).
 */
class CargoExterno extends \TCPDF
{
    const MX = 10;        // margen izquierdo/derecho (mm)
    const ANCHO = 190;    // ancho útil (mm)
    const QR = 24;        // tamaño del QR (mm)
    private static $AZUL = array(30, 79, 156);

    public function Header()
    {
        // el encabezado se dibuja en el cuerpo
    }

    public function Footer()
    {
        $this->SetY(-10);
        $this->SetFont('helvetica', 'I', 6.5);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 5, 'SISGEDO - Sistema de Gestión Documentario · Cargo de recepción', 0, false, 'C');
    }

    private static function e($v)
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }

    /** Recorta textos largos para que el cargo nunca pase de una hoja. */
    private static function acotar($v, $max)
    {
        $v = trim(preg_replace('/\s+/u', ' ', (string)$v));
        return mb_strlen($v, 'UTF-8') > $max ? rtrim(mb_substr($v, 0, $max, 'UTF-8')) . '…' : $v;
    }

    /** Tabla de datos (HTML) en la columna izquierda; devuelve la Y final. */
    private function tablaDatos($y, array $filas)
    {
        $borde = 'border:0.25px solid #B9C2CF;';
        $lab = 'style="' . $borde . 'background-color:#F1F4F8;font-size:6.6px;font-weight:bold;color:#333333;"';
        $val = 'style="' . $borde . 'font-size:8.2px;font-weight:normal;"';
        $h = '<table cellpadding="2.2" cellspacing="0">';
        foreach ($filas as $f) {
            $h .= '<tr>';
            if (count($f) === 4) {
                $h .= '<td width="17%" ' . $lab . '>' . self::e($f[0]) . '</td><td width="33%" ' . $val . '>' . $f[1] . '</td>'
                    . '<td width="17%" ' . $lab . '>' . self::e($f[2]) . '</td><td width="33%" ' . $val . '>' . $f[3] . '</td>';
            } else {
                $h .= '<td width="17%" ' . $lab . '>' . self::e($f[0]) . '</td><td width="83%" colspan="3" ' . $val . '>' . $f[1] . '</td>';
            }
            $h .= '</tr>';
        }
        $h .= '</table>';
        $this->SetDrawColor(185, 194, 207);
        $this->SetLineWidth(0.2);
        $this->SetFont('helvetica', '', 8.2);
        $this->writeHTMLCell(self::ANCHO - self::QR - 5, 0, self::MX, $y, $h, 0, 1, false, true, 'L');
        return $this->GetY();
    }

    /** Rótulo "CARGO DE RECEPCIÓN" + etiqueta azul a la derecha. */
    private function rotulo($y, $etiqueta)
    {
        $az = self::$AZUL;
        $this->SetTextColor(20, 20, 20);
        $this->SetFont('helvetica', 'B', 8.5);
        $this->SetXY(self::MX, $y);
        $this->Cell(100, 4.5, 'CARGO DE RECEPCIÓN', 0, 0, 'L');
        $this->SetFont('helvetica', 'B', 6.5);
        $w = $this->GetStringWidth($etiqueta) + 5;
        $this->SetFillColor($az[0], $az[1], $az[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(self::MX + self::ANCHO - $w, $y + 0.4);
        $this->Cell($w, 3.8, $etiqueta, 0, 0, 'C', true);
        $this->SetTextColor(20, 20, 20);
        return $y + 5.5;
    }

    /** QR de verificación con su código debajo, en la columna derecha. */
    private function qr($y, $cvd, $leyenda)
    {
        if (!$cvd) return $y;
        $x = self::MX + self::ANCHO - self::QR - 1;
        $this->write2DBarcode(ArchivoService::urlVerificacion($cvd), 'QRCODE,M', $x, $y, self::QR, self::QR,
            array('border' => false, 'padding' => 0, 'fgcolor' => array(0, 0, 0), 'bgcolor' => false), 'N');
        $this->SetFont('courier', 'B', 8.5);
        $this->SetXY($x - 3, $y + self::QR + 0.5);
        $this->Cell(self::QR + 6, 3.5, ArchivoService::formatoCvd($cvd), 0, 2, 'C');
        $this->SetFont('helvetica', '', 5.8);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(self::QR + 6, 2.8, $leyenda, 0, 0, 'C');
        $this->SetTextColor(20, 20, 20);
        return $y + self::QR + 7;
    }

    private function pieBloque($y, $izq, $der)
    {
        $this->SetFont('helvetica', '', 6.6);
        $this->SetTextColor(70, 70, 70);
        $this->SetXY(self::MX, $y);
        $this->Cell(self::ANCHO / 2, 3.5, $izq, 0, 0, 'L');
        $this->Cell(self::ANCHO / 2, 3.5, $der, 0, 0, 'R');
        $this->SetTextColor(20, 20, 20);
        return $y + 4;
    }

    /** Genera y envía el PDF. $d = CargoModel::externo() o null; $numexp para el aviso. */
    public static function generar($d, $numexp)
    {
        $pdf = new self('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('SISGEDO');
        $pdf->SetTitle('Cargo de recepción');
        $pdf->SetSubject('Sistema de Gestión Documentario');
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(self::MX, 8, self::MX);
        $pdf->SetAutoPageBreak(false);           // una sola hoja, siempre
        $pdf->setCellPaddings(0, 0, 0, 0);
        $pdf->AddPage();
        $MX = self::MX;
        $ANCHO = self::ANCHO;
        $az = self::$AZUL;

        if (!$d) {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetXY($MX, 40);
            $pdf->Cell($ANCHO, 8, 'No se encontró el expediente N° ' . $numexp, 0, 0, 'C');
            $pdf->Output('Cargo.pdf', 'I');
            return;
        }

        $sitio = rtrim(preg_replace('~^https?://~', '', Url::absoluta('')), '/');
        $documento = self::e($d['tipo']) . ($d['numero'] !== '' ? ' <b>' . self::e($d['numero']) . '</b>' : '');

        // 1) Encabezado
        $pdf->Image(K_PATH_IMAGES . SISGEDO_PDF_LOGO, $MX, 8, 0, 11, 'PNG', '', '', false, 300);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetXY($MX + 40, 8.5);
        $pdf->Cell(100, 5, SISGEDO_ENTIDAD_MAYUS, 0, 2, 'L');
        $pdf->SetFont('helvetica', '', 7.2);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->Cell(100, 4, 'Sistema de Gestión Documentario · Mesa de Partes', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 6.2);
        $pdf->SetXY($MX + $ANCHO - 60, 8);
        $pdf->Cell(60, 3, 'EXPEDIENTE', 0, 2, 'R');
        $pdf->SetFont('courier', 'B', 16);
        $pdf->SetTextColor($az[0], $az[1], $az[2]);
        $pdf->Cell(60, 7, $d['exp'], 0, 0, 'R');
        $pdf->SetTextColor(20, 20, 20);
        $pdf->SetDrawColor($az[0], $az[1], $az[2]);
        $pdf->SetLineWidth(0.6);
        $pdf->Line($MX, 21, $MX + $ANCHO, 21);

        // 2) Talón del ciudadano
        $y = $pdf->rotulo(23, 'TALÓN DEL CIUDADANO');
        $y0 = $y;
        $yT = $pdf->tablaDatos($y, array(
            array('FECHA Y HORA', self::e($d['fecha'] . ' · ' . $d['hora']), 'FOLIOS', self::e($d['folios'])),
            array('DOCUMENTO', $documento),
            array('DERIVADO A', '<b>' . self::e($d['derivar']) . '</b>', 'CANAL', self::e($d['canal'])),
            array('ASUNTO', self::e(self::acotar($d['asunto'], 150))),
        ));
        $yQ = $pdf->qr($y0, $d['cvd'], 'Escanee para ver el estado');
        $y = $pdf->pieBloque(max($yT, $yQ) + 0.8, 'Consulte su trámite con el N° de expediente en ' . $sitio, '');

        // Línea de corte
        $y += 2;
        $pdf->SetDrawColor(140, 150, 165);
        $pdf->SetLineStyle(array('width' => 0.3, 'dash' => '1.5,1.2', 'color' => array(140, 150, 165)));
        $pdf->Line($MX, $y, $MX + $ANCHO, $y);
        $pdf->SetLineStyle(array('width' => 0.2, 'dash' => 0));
        $pdf->SetFont('helvetica', '', 6.2);
        $pdf->SetTextColor(110, 120, 135);
        $txt = 'CORTAR Y ENTREGAR AL CIUDADANO';
        $tw = $pdf->GetStringWidth($txt) + 6;
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetXY($MX + ($ANCHO - $tw) / 2, $y - 1.7);
        $pdf->Cell($tw, 3.4, $txt, 0, 0, 'C', true);
        $pdf->SetTextColor(20, 20, 20);

        // 3) Ejemplar de la entidad · hoja de ruta
        $y = $pdf->rotulo($y + 3, 'EJEMPLAR DE LA ENTIDAD · HOJA DE RUTA');
        $y0 = $y;
        $yT = $pdf->tablaDatos($y, array(
            array('FECHA Y HORA', self::e($d['fecha'] . ' · ' . $d['hora']), 'FOLIOS', self::e($d['folios'])),
            array('REMITENTE', self::e($d['remitente'] !== '' ? $d['remitente'] : '—')),
            array('DOCUMENTO', $documento),
            array('TRÁMITE', self::e($d['tramite']), 'DERIVADO A', '<b>' . self::e($d['derivar']) . '</b>'),
            array('ASUNTO', self::e(self::acotar($d['asunto'], 420))),
        ));
        $yQ = $pdf->qr($y0, $d['cvd'], 'Verificar este cargo');
        $y = $pdf->pieBloque(max($yT, $yQ) + 0.8, 'Registró: ' . self::acotar($d['registro'], 70), 'Verificación: ' . $sitio . '/verificar');

        // 4) Recorrido: casillas de sello
        $y += 2;
        $pdf->SetDrawColor($az[0], $az[1], $az[2]);
        $pdf->SetLineWidth(0.5);
        $pdf->Line($MX, $y, $MX + $ANCHO, $y);
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetXY($MX, $y + 1.2);
        $pdf->Cell(120, 4.5, 'RECORRIDO · SELLO DE RECEPCIÓN DE CADA ÁREA', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 6.4);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->Cell($ANCHO - 120, 4.5, 'El sistema también registra cada derivación', 0, 0, 'R');
        $pdf->SetTextColor(20, 20, 20);

        $yIni = $y + 7;
        $yFin = 297 - 12;
        $cols = 3;
        $filas = (defined('SISGEDO_CARGO_CASILLAS') && (int)SISGEDO_CARGO_CASILLAS === 9) ? 3 : 4;
        $cw = $ANCHO / $cols;
        $ch = ($yFin - $yIni) / $filas;
        for ($i = 0; $i < $cols * $filas; $i++) {
            $cx = $MX + ($i % $cols) * $cw;
            $cy = $yIni + floor($i / $cols) * $ch;
            $pdf->SetFillColor(241, 244, 248);
            $pdf->SetDrawColor(195, 203, 214);
            $pdf->SetLineWidth(0.2);
            $pdf->Rect($cx, $cy, $cw, 4.2, 'DF');
            $pdf->SetFont('helvetica', 'B', 6.6);
            $pdf->SetXY($cx + 1.5, $cy + 0.4);
            $pdf->Cell(20, 3.4, 'N° ' . ($i + 1), 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 6);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->SetXY($cx + $cw - 31.5, $cy + 0.4);
            $pdf->Cell(30, 3.4, 'Exp. ' . $d['exp'], 0, 0, 'R');
            $pdf->SetFont('helvetica', '', 6);
            $pdf->SetTextColor(211, 217, 226);
            $pdf->SetXY($cx, $cy + 4.2 + ($ch - 4.2 - 8) / 2 - 1.5);
            $pdf->Cell($cw, 3, 'SELLO DE RECEPCIÓN', 0, 0, 'C');
            $pdf->SetTextColor(20, 20, 20);
            $yl = $cy + $ch - 8;
            $pdf->SetLineStyle(array('width' => 0.2, 'dash' => '0.6,0.6', 'color' => array(195, 203, 214)));
            $pdf->Line($cx, $yl, $cx + $cw, $yl);
            $pdf->SetLineStyle(array('width' => 0.2, 'dash' => 0, 'color' => array(205, 211, 220)));
            $pdf->SetFont('helvetica', '', 6.2);
            $pdf->SetTextColor(85, 85, 85);
            $pdf->SetXY($cx + 1.5, $yl + 0.6);
            $pdf->Cell(9, 3.3, 'Área:', 0, 0, 'L');
            $pdf->Line($cx + 9, $yl + 3.3, $cx + $cw - 1.5, $yl + 3.3);
            $pdf->SetXY($cx + 1.5, $yl + 4.2);
            $pdf->Cell(20, 3.3, 'Fecha / N° reg.:', 0, 0, 'L');
            $pdf->Line($cx + 19.5, $yl + 6.9, $cx + $cw - 1.5, $yl + 6.9);
            $pdf->SetTextColor(20, 20, 20);
            $pdf->SetDrawColor(195, 203, 214);
            $pdf->Rect($cx, $cy, $cw, $ch, 'D');
        }
        $pdf->SetDrawColor(154, 165, 181);
        $pdf->SetLineWidth(0.35);
        $pdf->Rect($MX, $yIni, $ANCHO, $yFin - $yIni, 'D');
        $pdf->Output('Cargo.pdf', 'I');
    }
}
