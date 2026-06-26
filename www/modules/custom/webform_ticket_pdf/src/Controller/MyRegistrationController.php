<?php

namespace Drupal\webform_ticket_pdf\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MyRegistrationController extends ControllerBase {

  public function page(WebformSubmissionInterface $webform_submission = NULL) {
    $webform_id = $this->getWebformId(); //ExhibitorRegistrationController overrules this

    if (!$webform_id) {
      throw new NotFoundHttpException($this->getMissingWebformMessage());
    }

    $isVisitor = 'visitor' === webform_ticket_pdf_type($webform_id) ? true : false;

    if ($isVisitor) {
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
        return $this->getNewSubmissionResponse($webform_id);
      }

      $sid = reset($ids);
      $submission = WebformSubmission::load($sid);
    }
    else {
      if ($webform_submission) {
        $submission = $webform_submission;
      }
      else {
        return $this->getNewSubmissionResponse($webform_id);
      }
    }

    if (!$submission) {
      throw new NotFoundHttpException('Registration not found.');
    }

    $build = [];

    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['ticket-actions'],
      ],
      '#weight' => -10,
    ];

    $build['actions']['download_ticket'] = [
      '#type' => 'link',
      '#title' => $this->t('Download ticket'),
      '#url' => Url::fromRoute('webform_ticket_pdf.download_ticket', [
        'webform' => $submission->getWebform()->id(),
        'webform_submission' => $submission->id(),
      ], [
        'query' => [
          'download' => 1,
        ],
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

    if ($isVisitor) {
      $build['form'] = $this->entityFormBuilder()
        ->getForm($submission, 'edit');
    }

    return $build;
  }

  protected function getWebformId(): ?string {
    return \Drupal::service('domain_settings.manager')
      ->getTicketWebformId();
  }

  protected function getMissingWebformMessage(): string {
    return 'No ticket Webform configured for this domain.';
  }

  protected function getNewSubmissionResponse(string $webform_id) {
    $url = Url::fromUri('internal:/webform/' . $webform_id)->toString();
    return new RedirectResponse($url);
  }

  protected function getRegistrationType() {   
    return 'visitor';
  }

}
