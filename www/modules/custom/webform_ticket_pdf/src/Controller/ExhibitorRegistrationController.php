<?php

namespace Drupal\webform_ticket_pdf\Controller;

use Drupal\webform\Entity\Webform;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExhibitorRegistrationController extends MyRegistrationController {

  protected function getWebformId(): ?string {
    return \Drupal::service('domain_settings.manager')
      ->getTicketExhibitorWebformId();
  }

  protected function getMissingWebformMessage(): string {
    return 'No exhibitor Webform configured for this domain.';
  }

  protected function getNewSubmissionResponse(string $webform_id) {
    $webform = Webform::load($webform_id);

    if (!$webform) {
      throw new NotFoundHttpException('Configured exhibitor Webform was not found.');
    }

    if (!$webform->access('submission_page')) {
      throw new AccessDeniedHttpException();
    }

    return $webform->getSubmissionForm();
  }

}
