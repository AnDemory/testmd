<?php

namespace Drupal\webform_ticket_pdf\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TicketDownloadController extends ControllerBase {

  // public function download(WebformSubmissionInterface $webform_submission) {
  //   $webform_id = $webform_submission->getWebform()->id();
  //   $sid = $webform_submission->id();

  //   $directory = \Drupal::config('webform_ticket_pdf.settings')
  //     ->get('output_directory') ?: 'private://tickets';

  //   $uri = $directory . '/' . $webform_id . '-ticket-' . $sid . '.pdf';

  //   $path = \Drupal::service('file_system')->realpath($uri);

  //   if (!$path || !file_exists($path)) {
  //     throw new NotFoundHttpException('Ticket PDF file not found: ' . $uri);
  //   }

  //   $response = new BinaryFileResponse($path);
  //   $response->headers->set('Content-Type', 'application/pdf');
  //   $response->headers->set(
  //     'Content-Disposition',
  //     'inline; filename="' . $webform_id . '-ticket-' . $sid . '.pdf"'
  //   );

  //   $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
  //   $response->headers->set('Pragma', 'no-cache');
  //   $response->headers->set('Expires', '0');

  //   return $response;
  // }
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
    // $response->headers->set(
    //   'Content-Disposition',
    //   'inline; filename="ticket-' . $webform_submission->id() . '.pdf"'
    // );
    $response->headers->set(
      'Content-Disposition',
      $disposition . '; filename="ticket-' . $webform_submission->id() . '.pdf"'
    );

    return $response;
  }

  public function access(WebformSubmissionInterface $webform_submission, AccountInterface $account) {
    if ($account->hasPermission('administer webform submission')) {
      return AccessResult::allowed();
    }

    if ($account->hasPermission('view any webform submission')) {
      return AccessResult::allowed();
    }

    if ($account->hasPermission('view own webform submission')) {
      return AccessResult::allowed();
    }

    if ($webform_submission->getOwnerId() == $account->id()) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
