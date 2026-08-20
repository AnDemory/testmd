<?php

namespace Drupal\webform_ticket_pdf\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TicketDownloadController extends ControllerBase {

  public function download(WebformSubmissionInterface $webform_submission, Request $request) {
    $domain = webform_ticket_pdf_get_domain_for_submission($webform_submission);

    if (!$domain) {
      throw new NotFoundHttpException('No ticket domain found for this submission.');
    }

    $pdf_content = \Drupal::service('webform_ticket_pdf.generator')
      ->generate($webform_submission, $domain);

    $disposition = $request->query->getBoolean('download')
      ? 'attachment'
      : 'inline';

    $response = new Response($pdf_content);
    $response->headers->set('Content-Type', 'application/pdf');

    $response->headers->set(
      'Content-Disposition',
      $disposition . '; filename="ticket-' . $webform_submission->id() . '.pdf"'
    );

    return $response;
  }

}
