<?php

namespace Drupal\webform_ticket_pdf\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TicketEmailController extends ControllerBase {

  public function access(WebformInterface $webform, WebformSubmissionInterface $webform_submission, AccountInterface $account) {
    if ($webform_submission->getWebform()->id() !== $webform->id()) {
      return AccessResult::forbidden();
    }

    if ($account->hasPermission('administer webform submission')) {
      return AccessResult::allowed();
    }

    if ($account->hasPermission('view any webform submission')) {
      return AccessResult::allowed();
    }

    if ((int) $webform_submission->getOwnerId() === (int) $account->id()) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  public function send(WebformInterface $webform, WebformSubmissionInterface $webform_submission) {
    if ($webform_submission->getWebform()->id() !== $webform->id()) {
      throw new NotFoundHttpException('Submission not found.');
    }

    // Regenerate the PDF before sending, so edited data is included.
    /** @var \Drupal\webform_ticket_pdf\TicketPdfGenerator $generator */
    $generator = \Drupal::service('webform_ticket_pdf.generator');
    $pdf_uri = $generator->generate($webform_submission);

    $pdf_path = \Drupal::service('file_system')->realpath($pdf_uri);

    if (!$pdf_path || !file_exists($pdf_path)) {
      throw new NotFoundHttpException('Ticket PDF file not found.');
    }

    $data = $webform_submission->getData();

    // Adjust this to your Webform email field machine name.
    $to = $data['email'] ?? $webform_submission->getOwner()->getEmail();

    if (!$to) {
      $this->messenger()->addError($this->t('No email address found for this submission.'));
      return $this->redirectBackToSubmission($webform, $webform_submission);
    }

    $params = [
      'submission' => $webform_submission,
      'pdf_path' => $pdf_path,
      'pdf_filename' => $webform->id() . '-ticket-' . $webform_submission->id() . '.pdf',
    ];

    $langcode = $this->currentUser()->getPreferredLangcode();

    $result = \Drupal::service('plugin.manager.mail')->mail(
      'webform_ticket_pdf',
      'ticket_email',
      $to,
      $langcode,
      $params,
      NULL,
      TRUE
    );

    if (!empty($result['result'])) {
      $this->messenger()->addStatus($this->t('The ticket email has been sent to @mail.', [
        '@mail' => $to,
      ]));
    }
    else {
      $this->messenger()->addError($this->t('The ticket email could not be sent.'));
    }

    return $this->redirectBackToSubmission($webform, $webform_submission);
  }

  protected function redirectBackToSubmission(WebformInterface $webform, WebformSubmissionInterface $webform_submission): RedirectResponse {
    $url = Url::fromUri('internal:/webform/' . $webform->id() . '/submissions/' . $webform_submission->id())
      ->toString();

    return new RedirectResponse($url);
  }

}
