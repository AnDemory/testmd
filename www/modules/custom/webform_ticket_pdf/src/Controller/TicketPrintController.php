<?php

namespace Drupal\webform_ticket_pdf\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TicketPrintController extends ControllerBase {

  public function print(WebformSubmissionInterface $webform_submission) {
    if (!function_exists('webform_ticket_pdf_get_domain_for_submission')) {
      throw new NotFoundHttpException('Ticket domain helper not available.');
    }

    $domain = webform_ticket_pdf_get_domain_for_submission($webform_submission);

    if (!$domain) {
      throw new NotFoundHttpException('No ticket domain found for this submission.');
    }

    $pdf_url = Url::fromRoute('webform_ticket_pdf.download_ticket', [
      'webform' => $webform_submission->getWebform()->id(),
      'webform_submission' => $webform_submission->id(),
    ], [
      'absolute' => TRUE,
    ])->toString();

    $html = '<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Print ticket</title>
  <style>
    html, body {
      margin: 0;
      padding: 0;
      height: 100%;
    }

    iframe {
      width: 100%;
      height: 100%;
      border: 0;
    }
  </style>
</head>
<body>
  <iframe id="ticket-pdf" src="' . htmlspecialchars($pdf_url, ENT_QUOTES, 'UTF-8') . '"></iframe>
  <script>
    const iframe = document.getElementById("ticket-pdf");

    iframe.addEventListener("load", function () {
      setTimeout(function () {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
      }, 500);
    });
  </script>
</body>
</html>';

    return new Response($html);
  }

}
