<?php

namespace Drupal\webform_ticket_pdf\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MyRegistrationController extends ControllerBase {

  public function redirectToSubmission() {
    $webform_id = 'aquarama_trade_fair_2025';
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
      // No submission yet: send user to the registration form.
      $url = Url::fromUri('internal:/webform/' . $webform_id)->toString();
      return new RedirectResponse($url);
    }

    $sid = reset($ids);

    // View submission.
    $url = Url::fromUri('internal:/webform/' . $webform_id . '/submissions/' . $sid)->toString();

    // Or, if you want edit page instead, use this:
    // $url = Url::fromUri('internal:/webform/' . $webform_id . '/submissions/' . $sid . '/edit')->toString();

    return new RedirectResponse($url);
  }

}