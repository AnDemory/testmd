<?php

namespace Drupal\webform_ticket_pdf\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MyRegistrationController extends ControllerBase {

  public function page() {
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

    /** @var \Drupal\webform\WebformSubmissionInterface $submission */
    $submission = WebformSubmission::load($sid);

    if (!$submission) {
      throw new NotFoundHttpException('Registration not found.');
    }

    $build = [];

    $build['form'] = $this->entityFormBuilder()
      ->getForm($submission, 'edit');

    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['ticket-actions'],
      ],
      '#weight' => 100,
    ];

    $build['actions']['download_ticket'] = [
      '#type' => 'link',
      '#title' => $this->t('Download ticket'),
      '#url' => Url::fromRoute('webform_ticket_pdf.download_ticket', [
        'webform' => $submission->getWebform()->id(),
        'webform_submission' => $submission->id(),
      ]),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
        'target' => '_blank',
      ],
    ];

    $build['actions']['email_ticket'] = [
      '#type' => 'link',
      '#title' => $this->t('Email me the ticket'),
      '#url' => Url::fromRoute('webform_ticket_pdf.email_ticket', [
        'webform' => $submission->getWebform()->id(),
        'webform_submission' => $submission->id(),
      ]),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    return $build;
  }

}
