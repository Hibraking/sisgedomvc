<?php
namespace App\Pdf;

/** Cabecera y pie de los cuadernos (reporte diario), igual que sisgedov2/tcpdf/examples/reporte_diario*.php. */
class ReporteDiario extends \TCPDF
{
    public function Header()
    {
        $this->Image(K_PATH_IMAGES . SISGEDO_PDF_LOGO, 8, 8, 35, 10, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 15, SISGEDO_ENTIDAD_MAYUS, 0, false, 'C', 0, '', 0, false, 'C', 'M');
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}
