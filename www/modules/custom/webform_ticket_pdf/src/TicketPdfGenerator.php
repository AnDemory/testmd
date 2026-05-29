<?php

namespace Drupal\webform_ticket_pdf;

use Drupal\Core\File\FileSystemInterface;
use Drupal\webform\WebformSubmissionInterface;
use TCPDF;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

use Picqer\Barcode\BarcodeGeneratorPNG;

class TicketPdfGenerator {

  protected FileSystemInterface $fileSystem;

  public function __construct(FileSystemInterface $fileSystem) {
    $this->fileSystem = $fileSystem;
  }

  public function generate(WebformSubmissionInterface $submission): string {

    $data = $submission->getData();

    $name = $data['name'] ?? 'An Demory';
    $company = $data['company'] ?? 'FCO Media';

    $sid = $submission->id();

    // -------------------------------------------------
    // CREATE DIRECTORY
    // -------------------------------------------------

    $directory = 'private://tickets';

    $this->fileSystem->prepareDirectory(
      $directory,
      FileSystemInterface::CREATE_DIRECTORY |
      FileSystemInterface::MODIFY_PERMISSIONS
    );

    // -------------------------------------------------
    // GENERATE QR CODE
    // -------------------------------------------------

    $qr_path = $this->generateQrCode($sid);

    // -------------------------------------------------
    // GENERATE BARCODE
    // -------------------------------------------------

    $barcode_path = $this->generateBarcode($sid);

    // -------------------------------------------------
    // CREATE PDF
    // -------------------------------------------------

    $pdf = new TCPDF('P', 'mm', 'A4');

    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false, 0);

    $pdf->AddPage();

    // -------------------------------------------------
    // ADD JPG BACKGROUND
    // -------------------------------------------------

    $background = DRUPAL_ROOT .
      '/sites/default/files/ATF2025-visitorbadge-nl.jpg';

    $pdf->Image(
        $background,
        0,
        0,
        210,
        297,
        'JPG'
    );

    // -------------------------------------------------
    // TEXT SETTINGS
    // -------------------------------------------------

    $pdf->SetFont('helvetica', '', 18);
    $pdf->SetTextColor(0, 0, 0);

    // -------------------------------------------------
    // NAME
    // -------------------------------------------------

    $pdf->SetXY(40, 80);
    $pdf->Write(0, $name);

    // -------------------------------------------------
    // COMPANY
    // -------------------------------------------------

    $pdf->SetXY(40, 95);
    $pdf->Write(0, $company);

    // -------------------------------------------------
    // BARCODE
    // -------------------------------------------------

    $pdf->Image(
      $barcode_path,
      40,
      130,
      80,
      20,
      'PNG'
    );

    // -------------------------------------------------
    // QR CODE
    // -------------------------------------------------

    $pdf->Image(
        $qr_path,
        150,  // x: from left
        220,  // y: from top
        35,   // width
        35,   // height
        'PNG'
    );

    // -------------------------------------------------
    // SAVE PDF
    // -------------------------------------------------

    $output_uri = $directory . '/ticket-' . $sid . '.pdf';

    $output_path = $this->fileSystem
      ->realpath($output_uri);

    $pdf->Output($output_path, 'F');

    return $output_uri;
  }

  // =====================================================
  // QR CODE
  // =====================================================

  protected function generateQrCode($sid): string {

    $url = 'https://example.com/check-ticket/' . $sid;

    $qrCode = new QrCode(
        data: $url,
        size: 300,
        margin: 10
    );

    $writer = new PngWriter();

    $result = $writer->write($qrCode);

    $path = sys_get_temp_dir() . '/qr-' . $sid . '.png';

    $result->saveToFile($path);

    return $path;
    }
  

  // =====================================================
  // BARCODE
  // =====================================================

  protected function generateBarcode($sid): string {

    // EAN13 requires 12 digits
    $ean = str_pad((string) $sid, 12, '0', STR_PAD_LEFT);

    $generator = new BarcodeGeneratorPNG();

    $barcode = $generator->getBarcode(
      $ean,
      $generator::TYPE_EAN_13
    );

    $path = sys_get_temp_dir() .
      '/barcode-' . $sid . '.png';

    file_put_contents($path, $barcode);

    return $path;
  }

}