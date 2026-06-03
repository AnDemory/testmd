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

  // protected function getBackgroundTemplate(array $data, string $webform_id): string {
  //   $special_option = !empty($data['special_option']);


  //   if ($webform_id === 'aquarama_trade_fair_2025') {

  //       return DRUPAL_ROOT . '/sites/default/files/tickets/ATF2025-visitorbadge-nl.jpg';

  //   }

  //   if ($webform_id === 'another_event_webform') {
  //     return DRUPAL_ROOT . '/sites/default/files/tickets/another-event.jpg';
  //   }

  //   throw new \RuntimeException('No ticket template configured for webform: ' . $webform_id);
  // }
  protected function getBackgroundTemplate(array $data, string $webform_id): string {
    $configured_webform_id = \Drupal::service('domain_settings.manager')
      ->getTicketWebformId();

    if ($configured_webform_id !== $webform_id) {
      throw new \RuntimeException('This Webform is not configured for ticket PDFs: ' . $webform_id);
    }

    $template_uri = \Drupal::service('domain_settings.manager')
      ->getTicketTemplate();

    if (!$template_uri) {
      throw new \RuntimeException('No ticket template configured for webform: ' . $webform_id);
    }

    $template_path = \Drupal::service('file_system')->realpath($template_uri);

    if (!$template_path || !file_exists($template_path)) {
      throw new \RuntimeException('Ticket template file not found: ' . $template_uri);
    }

    return $template_path;
  }

  public function generate(WebformSubmissionInterface $submission): string {

    $data = $submission->getData();

    $name = $data['name'] ?? 'An Demory';
    $company = $data['company'] ?? 'FCO Media';
    $account = $submission->getOwner();

    $name = $account->get('field_first_name')->value . " ". $account->get('field_name')->value;

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

    $webform_id = $submission->getWebform()->id();
    $background = $this->getBackgroundTemplate($data, $webform_id);

    $pdf->Image($background, 0, 0, 210, 297, 'JPG');

    // -------------------------------------------------
    // TEXT SETTINGS
    // -------------------------------------------------

    $pdf->SetFont('helvetica', '', 18);
    $pdf->SetTextColor(0, 0, 0);

    // -------------------------------------------------
    // NAME
    // -------------------------------------------------

    $pdf->SetXY(35, 208);
    $pdf->Write(0, $name);
    $pdf->SetXY(120, 208);
    $pdf->Write(0, $name);

    // -------------------------------------------------
    // COMPANY
    // -------------------------------------------------

    $pdf->SetXY(35, 218);
    $pdf->Write(0, $company);
    $pdf->SetXY(120, 218);
    $pdf->Write(0, $company);

    // -------------------------------------------------
    // BARCODE
    // -------------------------------------------------

    $pdf->Image(
      $barcode_path,
      120,
      20,
      60,
      20,
      'PNG'
    );

    // -------------------------------------------------
    // QR CODE
    // -------------------------------------------------

    $pdf->Image(
      $qr_path,
      150,
      240,
      30,
      30,
      'PNG'
    );
    $pdf->Image(
      $qr_path,
      40,
      240,
      30,
      30,
      'PNG'
    );

    // -------------------------------------------------
    // SAVE PDF
    // -------------------------------------------------

    $webform_id = $submission->getWebform()->id();
    $output_uri = $directory . '/' . $webform_id . '-ticket-' . $sid . '.pdf';

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

    $qrCode = new QrCode($url);

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
