<?php

namespace Drupal\webform_ticket_pdf\Controller;

use Drupal\webform\Entity\Webform;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExhibitorRegistrationController extends MyRegistrationController {

// public function page() {
//     $webform_id = $this->getWebformId();

//     if (!$webform_id) {
//       throw new NotFoundHttpException($this->getMissingWebformMessage());
//     }


//     /** @var \Drupal\webform\WebformSubmissionInterface $submission */
//     $submission = WebformSubmission::load($sid);

//     if (!$submission) {
//       throw new NotFoundHttpException('Registration not found.');
//     }

//     $build = [];

//     $build['form'] = $this->entityFormBuilder()
//       ->getForm($submission, 'edit');

//     $build['actions'] = [
//       '#type' => 'container',
//       '#attributes' => [
//         'class' => ['ticket-actions'],
//       ],
//       '#weight' => 0,
//     ];

//     $build['actions']['download_ticket'] = [
//       '#type' => 'link',
//       '#title' => $this->t('Download ticket'),
//       '#url' => Url::fromRoute('webform_ticket_pdf.download_ticket', [
//         'webform' => $submission->getWebform()->id(),
//         'webform_submission' => $submission->id(),
//       ], [
//         'query' => [
//           'download' => 1,
//         ],
//       ]),
//       '#attributes' => [
//         'class' => ['button', 'button--primary'],
//         'target' => '_blank',
//       ],
//     ];

//     $build['actions']['email_ticket'] = [
//       '#type' => 'link',
//       '#title' => $this->t('Email me the ticket'),
//       '#url' => Url::fromRoute('webform_ticket_pdf.email_ticket', [
//         'webform' => $submission->getWebform()->id(),
//         'webform_submission' => $submission->id(),
//       ]),
//       '#attributes' => [
//         'class' => ['button'],
//       ],
//     ];

//     return $build;
//   }

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

   protected function getRegistrationType() {   
    return 'exhibitor';
  }

}
