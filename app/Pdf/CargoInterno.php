<?php
namespace App\Pdf;

use App\Core\Url;
use App\Services\ArchivoService;

/**
 * Cargo de recepción/derivación INTERNA (entre oficinas) — mismo diseño que
 * sisgedov2/pdf/cargo_interno.php: DOS copias del cuadro de datos (con su QR)
 * + cuadrícula 3x3 de sellos, en una sola hoja A4. Requiere TCPDF cargado.
 */
class CargoInterno extends \TCPDF
{
    public function Header()
    {
        $this->Image(K_PATH_IMAGES . SISGEDO_PDF_LOGO, 8, 8, 35, 10, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 15, SISGEDO_ENTIDAD_MAYUS, 0, false, 'C', 0, '', 0, false, 'C', 'M');
    }

    public function Footer()
    {
        $this->SetY(-13);
        $this->SetFont('helvetica', 'I', 7);
        $this->Cell(0, 8, 'SISGEDO - Sistema de Gestión Documentario · Cargo interno', 0, false, 'C');
    }

    private static function e($v)
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }

    private static function acotar($v, $max)
    {
        $v = trim((string)$v);
        return mb_strlen($v, 'UTF-8') > $max ? mb_substr($v, 0, $max, 'UTF-8') . '…' : $v;
    }

    /** Cuadrícula clásica de recepciones: 3 filas x 3 columnas. */
    private static function cuadricula()
    {
        $html = '<table border="1" cellpadding="3" cellspacing="0" width="100%"><tbody>';
        for ($fila = 0; $fila < 3; $fila++) {
            $html .= '<tr style="background-color:#F0F0F0;">';
            for ($c = 1; $c <= 3; $c++) {
                $html .= '<th colspan="2" width="33.33%" align="center"><b>Datos de Recepción ' . ($fila * 3 + $c) . '</b></th>';
            }
            $html .= '</tr><tr>';
            for ($c = 0; $c < 3; $c++) $html .= '<th width="9%" align="center"><b>N° EXP:</b></th><td width="24.33%">&nbsp;</td>';
            $html .= '</tr><tr>';
            for ($c = 0; $c < 3; $c++) $html .= '<th width="9%" align="center"><b>Obs.:</b></th><td width="24.33%">&nbsp;</td>';
            $html .= '</tr><tr>';
            for ($c = 0; $c < 3; $c++) $html .= '<td colspan="2" width="33.33%" height="58">&nbsp;</td>';
            $html .= '</tr><tr>';
            for ($c = 0; $c < 3; $c++) $html .= '<td colspan="2" width="33.33%" align="center">SELLO O V° B°</td>';
            $html .= '</tr>';
        }
        return $html . '</tbody></table>';
    }

    /** Fila del pie con el CVD y la dirección de verificación. */
    private static function filaVerificacion($cvd)
    {
        if (!$cvd) return '';
        $url = ArchivoService::urlVerificacion($cvd);
        return '<tr><td colspan="4" style="font-size:7.5px;">Código de verificación: <b>' . self::e(ArchivoService::formatoCvd($cvd)) . '</b> — '
            . 'Verifique la autenticidad de este cargo en <a href="' . self::e($url) . '">' . self::e(Url::absoluta('verificar')) . '</a></td></tr>';
    }

    private static function tabla(array $dato)
    {
        $f = 'style="background-color:#F0F0F0;"';
        $ident = $dato[9] === 'EXPEDIENTE'
            ? '<td align="right" ' . $f . '><b>EXPEDIENTE:</b></td><td><b>' . self::e($dato[10]) . '</b></td>'
            : '<td align="right" ' . $f . '><b>DOC. INTERNO N°:</b></td><td><b>' . self::e($dato[10]) . '</b></td>';
        return '<table border="1" cellpadding="3" width="80%">
            <thead><tr style="background-color:#F0F0F0;"><th colspan="4" align="center"><b>RECIBIDO EN ' . self::e($dato[8]) . '</b></th></tr></thead>
            <tbody>
                <tr>
                    <td align="right" width="20%" ' . $f . '><b>FECHA:</b></td><td width="30%">' . self::e($dato[0]) . '</td>
                    <td align="right" width="20%" ' . $f . '><b>HORA:</b></td><td width="30%">' . self::e($dato[1]) . '</td>
                </tr>
                <tr>' . $ident . '<td align="right" ' . $f . '><b>N° FOLIO:</b></td><td>' . self::e($dato[3]) . '</td></tr>
                <tr><td align="right" ' . $f . '><b>REG. DE LA OFICINA:</b></td><td colspan="3">' . self::e($dato[2]) . ' <span style="font-size:7px;color:#666666;">(número de registro interno de la oficina que lo recibió; no es el expediente)</span></td></tr>
                <tr>
                    <td align="right" ' . $f . '><b>TIPO DOCUMENTO:</b></td><td>' . self::e($dato[4]) . '</td>
                    <td align="right" ' . $f . '><b>NÚMERO:</b></td><td>' . self::e($dato[5]) . '</td>
                </tr>
                <tr><td align="right" ' . $f . '><b>TRÁMITE:</b></td><td colspan="3">' . self::e($dato[6]) . '</td></tr>
                <tr><td align="right" ' . $f . '><b>ASUNTO:</b></td><td colspan="3">' . self::e(self::acotar($dato[7], 200)) . '</td></tr>
            </tbody>
            <tfoot>' . self::filaVerificacion($dato['cvd']) . '</tfoot>
        </table>';
    }

    /** QR a la derecha del cuadro (que ocupa el 80% del ancho). */
    private function qr($y0, $cvd)
    {
        if (!$cvd) return;
        $m = $this->getMargins();
        $ancho = $this->getPageWidth() - $m['left'] - $m['right'];
        $tam = 25;
        $x = $m['left'] + $ancho * 0.8 + ($ancho * 0.2 - $tam) / 2;
        $yAntes = $this->GetY();
        $this->write2DBarcode(ArchivoService::urlVerificacion($cvd), 'QRCODE,M', $x, $y0 + 1, $tam, $tam,
            array('border' => false, 'padding' => 0, 'fgcolor' => array(0, 0, 0), 'bgcolor' => false), 'N');
        $this->SetFont('helvetica', 'B', 7);
        $this->SetXY($m['left'] + $ancho * 0.8, $y0 + $tam + 1.5);
        $this->Cell($ancho * 0.2, 3, ArchivoService::formatoCvd($cvd), 0, 2, 'C');
        $this->SetFont('helvetica', '', 6);
        $this->Cell($ancho * 0.2, 3, 'Escanee para verificar', 0, 0, 'C');
        $this->SetFont('times', '', 9);
        $this->SetXY($m['left'], max($yAntes, $y0 + $tam + 8));
    }

    /** $dato = CargoModel::interno() con la clave 'cvd' agregada, o null. */
    public static function generar($dato)
    {
        $pdf = new self(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('SISGEDO');
        $pdf->SetTitle('Cargo interno');
        $pdf->SetSubject('Sistema de Gestión Documentario');
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
        $pdf->SetCellPadding(1);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->SetFont('times', '', 9);
        $pdf->AddPage();
        if ($dato) {
            $tabla = self::tabla($dato);
            for ($copia = 0; $copia < 2; $copia++) {
                $y0 = $pdf->GetY();
                $pdf->writeHTML($tabla, true, false, true, false, '');
                $pdf->qr($y0, $dato['cvd']);
                $pdf->Ln(1);
            }
            $pdf->writeHTML(self::cuadricula(), true, false, true, false, '');
        } else {
            $pdf->writeHTML('<br><br><p align="center"><b>No se encontró el movimiento solicitado.</b></p>', true, false, true, false, '');
        }
        $pdf->Output('Cargo.pdf', 'I');
    }
}
