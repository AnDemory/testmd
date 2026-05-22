<?php

namespace Drupal\webform_ticket_pdf\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

class TicketDownloadController extends ControllerBase {

  public function download(WebformSubmissionInterface $webform_submission) {
    $data = $webform_submission->getData();

    if (empty($data['ticket_pdf'])) {
      throw new NotFoundHttpException('Ticket PDF not found.');
    }

    $uri = $data['ticket_pdf'];
    $path = \Drupal::service('file_system')->realpath($uri);

    if (!$path || !file_exists($path)) {
      throw new NotFoundHttpException('Ticket PDF file not found.');
    }

    $response = new BinaryFileResponse($path);
    $response->headers->set('Content-Type', 'application/pdf');
    $response->headers->set(
      'Content-Disposition',
      'inline; filename="ticket-' . $webform_submission->id() . '.pdf"'
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

    if ($webform_submission->getOwnerId() == $account->id()) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
