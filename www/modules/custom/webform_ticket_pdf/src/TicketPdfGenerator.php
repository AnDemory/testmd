<?php

namespace Drupal\webform_ticket_pdf;

use Drupal\Core\File\FileSystemInterface;
use Drupal\webform\WebformSubmissionInterface;
use TCPDF;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

use Picqer\Barcode\BarcodeGeneratorPNG;
use Jenssegers\Optimus\Optimus;


class TicketPdfGenerator {

  protected FileSystemInterface $fileSystem;

  public function __construct(FileSystemInterface $fileSystem) {
    $this->fileSystem = $fileSystem;
  }

  protected function getBackgroundTemplate(array $data, string $webform_id, ?string $domain = NULL): string {
    $configured_webform_id = \Drupal::service('domain_settings.manager')
      ->getTicketWebformId($domain);

    if ($configured_webform_id !== $webform_id) {

      $configured_webform_id = \Drupal::service('domain_settings.manager')
        ->getTicketExhibitorWebformId($domain);

      if ($configured_webform_id !== $webform_id) {
        throw new \RuntimeException('This Webform is not configured for ticket PDFs: ' . $configured_webform_id . '<->' .$webform_id);
      }
     
    }

    $template_uri = \Drupal::service('domain_settings.manager')
      ->getTicketTemplate($domain);


    //  \Drupal::logger('webform_ticket_pdf')->notice('Using ticket template: ' . $template_uri);

    if (!$template_uri) {
      throw new \RuntimeException('No ticket template configured for webform: ' . $webform_id);
    }

    $webform_type = webform_ticket_pdf_type($webform_id);
    $template_uri .= "-" . $webform_type;

    if ($domain == 'fm-day.ddev.site' && 'visitor' === $webform_type) {
      $template_uri .= "-" . $data['profile_custom'];
    }

    // add parameters to add to template name, depending on data values. For example, if there's a "language" field, you could do:
    $template_uri .= "-" . ($data['language'] ?? 'nl') . ".jpg";

     \Drupal::logger('webform_ticket_pdf')->notice('Resolved ticket template URI: ' . $template_uri);

    $template_path = \Drupal::service('file_system')->realpath($template_uri);

    if (!$template_path || !file_exists($template_path)) {
      throw new \RuntimeException('Ticket template file not found: ' . $template_uri);
    }

    return $template_path;
  }

  //public function generate(WebformSubmissionInterface $submission): string {
  public function generate(WebformSubmissionInterface $submission, ?string $domain = NULL): string {

    $data = $submission->getData();
    $webform_id = $submission->getWebform()->id();
    $is_visitor = 'visitor' === webform_ticket_pdf_type($webform_id) ? true : false;
    $fair_exhibitor = NULL;
    $qr_exhibitor = NULL;

    if ($is_visitor) {

      $name = $data['name'] ?? 'An Demory';
      $company = $data['company'] ?? 'FCO Media';
      $account = $submission->getOwner();

      $name = $account->get('field_first_name')->value . " ". $account->get('field_name')->value;

      $ean_type = "250";
      $base_id = $account->id();
    } else {
      $ean_type = "251";
      $base_id = $submission->id();

      $first_name = $data['first_name'] ?? '';
      $last_name = $data['last_name'] ?? '';
      $company = $data['company_name'] ?? '';

      $name = trim($first_name . ' ' . $last_name);

      // -------------------------------------------------
      // GENERATE IDENTIFY QR CODE
      // -------------------------------------------------

      define( 'FCO_APP_VENDOR_OPTIMUS_PRIME', 1514780639 );
      define( 'FCO_APP_VENDOR_OPTIMUS_INVERSE', 422888479 );
      define( 'FCO_APP_VENDOR_OPTIMUS_RANDOM', 1459426347 );
      # init optimus encryption library
      $optimus = new \Jenssegers\Optimus\Optimus(
        \FCO_APP_VENDOR_OPTIMUS_PRIME,
        \FCO_APP_VENDOR_OPTIMUS_INVERSE,
        \FCO_APP_VENDOR_OPTIMUS_RANDOM
      );

      # add auth ID and initial PIN to exhibitor data
      $fair_exhibitor['lrat_auth']     = $optimus->encode( $base_id );
      $fair_exhibitor['lrat_auth_pin'] = \mb_substr( $fair_exhibitor['lrat_auth'], 0, 4 );
\Drupal::logger("TicketPdfGenerator")->notice("base id".$base_id);
\Drupal::logger("TicketPdfGenerator")->notice("lrat id".$fair_exhibitor['lrat_auth'] );
      $qr_exhibitor = $this->generateQrCode('identify', $fair_exhibitor['lrat_auth']);
    }

    // // -------------------------------------------------
    // // CREATE DIRECTORY
    // // -------------------------------------------------

    // $directory = 'private://tickets';

    // $this->fileSystem->prepareDirectory(
    //   $directory,
    //   FileSystemInterface::CREATE_DIRECTORY |
    //   FileSystemInterface::MODIFY_PERMISSIONS
    // );

   

    // -------------------------------------------------
    // GENERATE BARCODE
    // -------------------------------------------------

    $eancode = $this->generateEAN($base_id, $ean_type);
    $barcode_path = $this->generateBarcode($eancode);

    // -------------------------------------------------
    // GENERATE QR CODE
    // -------------------------------------------------

    $qr_path = $this->generateQrCode('create', $eancode);

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

    
    $background = $this->getBackgroundTemplate($data, $webform_id, $domain);

    $pdf->Image($background, 0, 0, 210, 297, 'JPG');

    // -------------------------------------------------
    // TEXT SETTINGS
    // -------------------------------------------------

    $pdf->SetFont('helvetica', '', 18);
    $pdf->SetTextColor(0, 0, 0);

    // -------------------------------------------------
    // NAME
    // -------------------------------------------------


    // First name box.
    $pdf->SetXY(15, 208);
    $pdf->MultiCell(
      75,     // box width
      6,      // line height
      $name,  // text
      0,      // border: 0 = no border
      'C',    // align: center
      false,  // fill
      1       // move cursor to next line after
    );

    // Second name box.
    $pdf->SetXY(120, 208);
    $pdf->MultiCell(
      75,
      6,
      $name,
      0,
      'C',
      false,
      1
    );

    // -------------------------------------------------
    // COMPANY
    // -------------------------------------------------

    // $pdf->SetXY(35, 218);
    // $pdf->Write(0, $company);
    // $pdf->SetXY(120, 218);
    // $pdf->Write(0, $company);

    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(0, 0, 0);

    $pdf->SetXY(15, 225);
    $pdf->MultiCell(
      75,
      6,
      $company,
      0,
      'C',
      false,
      1
    );
    $pdf->SetXY(120, 225);
    $pdf->MultiCell(
      75,
      6,
      $company,
      0,
      'C',
      false,
      1
    );

    // -------------------------------------------------
    // BARCODE
    // -------------------------------------------------

    $pdf->Image(
      $barcode_path,
      133, // 105 + ((105 - 49)/2)
      18,
      49,
      17,
      'PNG'
    );

 

    // -------------------------------------------------
    // QR CODE
    // -------------------------------------------------

    $pdf->Image(
      $qr_path,
      38,
      240,
      29,
      29,
      'PNG'
    );
    $pdf->Image(
      $qr_path,
      143,
      240,
      29,
      29,
      'PNG'
    );

    if (!$is_visitor) {
      $pdf->Image(
        $qr_exhibitor,
        143,
        100,
        29,
        29,
        'PNG'
      );
    }

   // -------------------------------------------------
    // EANCODES
    // -------------------------------------------------

    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(164, 164, 164);
    $pdf->SetXY(120,37);
    $pdf->MultiCell(
      75,     // box width
      6,      // line height
      $eancode,  // text
      0,      // border: 0 = no border
      'C',    // align: center
      false,  // fill
      1       // move cursor to next line after
    );
    $pdf->SetXY(38,270);
    $pdf->MultiCell(
      29,     // box width
      6,      // line height
      $eancode,  // text
      0,      // border: 0 = no border
      'C',    // align: center
      false,  // fill
      1       // move cursor to next line after
    );
    $pdf->SetXY(143,270);
    $pdf->MultiCell(
      29,     // box width
      6,      // line height
      $eancode,  // text
      0,      // border: 0 = no border
      'C',    // align: center
      false,  // fill
      1       // move cursor to next line after
    );

    

    // // -------------------------------------------------
    // // SAVE PDF
    // // -------------------------------------------------

    // $webform_id = $submission->getWebform()->id();
    // $output_uri = $directory . '/' . $webform_id . '-ticket-' . $sid . '.pdf';

    // $output_path = $this->fileSystem
    //   ->realpath($output_uri);

    // $pdf->Output($output_path, 'F');

    // return $output_uri;

    // -------------------------------------------------
    // RETURN PDF AS STRING, DO NOT SAVE FILE
    // -------------------------------------------------

    return $pdf->Output('', 'S');
  }

  // =====================================================
  // QR CODE
  // =====================================================

  protected function generateQrCode($action, $eancode): string {
\Drupal::logger("TicketPdfGenerator")->notice("ecie".$eancode );
    $lead_retrieval_url = \Drupal::service('domain_settings.manager')
      ->getLeadRetrievalUrl($domain);
    $url = $lead_retrieval_url . $action . '/'. $eancode;

    $qrCode = new QrCode($url);

    $writer = new PngWriter();

    $result = $writer->write($qrCode);

    $path = sys_get_temp_dir() . '/qr-' . $eancode . '.png';

    $result->saveToFile($path);

    return $path;
  }

  // =====================================================
  // BARCODE
  // =====================================================

  protected function generateBarcode($ean): string {

    // EAN13 requires 12 digits
    // $ean = str_pad((string) $sid, 12, '0', STR_PAD_LEFT);

    $generator = new BarcodeGeneratorPNG();

    $barcode = $generator->getBarcode(
      $ean,
      $generator::TYPE_EAN_13
    );

    $path = sys_get_temp_dir() .
      '/barcode-' . $ean . '.png';

    file_put_contents($path, $barcode);

    return $path;
  }

  protected function generateEAN( $sid, $prefix = '250' ) {

    $ean       = $prefix . str_pad( (int) $sid, 9, '0', STR_PAD_LEFT );
    $weightflag = true;
    $sum        = 0;

    // Weight for a digit in the checksum is 3, 1, 3.. starting from the last digit.
    // loop backwards to make the loop length-agnostic. The same basic functionality
    // will work for codes of different lengths.
    for ( $i = strlen( $ean ) - 1; $i >= 0; $i-- ) {

      $sum += (int) $ean[ $i ] * ( $weightflag ? 3 : 1 );

      $weightflag = ! $weightflag;
    }

    $ean .= ( 10 - ( $sum % 10 ) ) % 10;

    return $ean;
  }

}


