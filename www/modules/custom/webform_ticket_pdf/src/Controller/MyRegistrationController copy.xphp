<?php

namespace Drupal\webform_ticket_pdf\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MyRegistrationController extends ControllerBase {

  public function redirectToSubmission() {
    $webform_id = \Drupal::service('domain_settings.manager')
      ->getTicketWebformId();

    if (!$webform_id) {
      throw new NotFoundHttpException('No ticket Webform configured for this domain.');
    }

    $uid = $this->currentUser()->id();

    $storage = $this->entityTypeManager()
      ->getStorage('webform_submission');

    $ids = $storage->getQuery()
      ->condition('webform_id', $webform_id)
      ->condition('uid', $uid)
      ->sort('created', 'DESC')
      ->range(0, 1)
      ->accessCheck(FALSE)
      ->execute();

    if (empty($ids)) {
      $url = Url::fromUri('internal:/webform/' . $webform_id)->toString();
      return new RedirectResponse($url);
    }

    $sid = reset($ids);

    $url = Url::fromUri('internal:/webform/' . $webform_id . '/submissions/' . $sid)->toString();

    return new RedirectResponse($url);
  }

}
