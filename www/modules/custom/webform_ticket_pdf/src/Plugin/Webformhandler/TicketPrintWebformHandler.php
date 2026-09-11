<?php

namespace Drupal\webform_ticket_pdf\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Redirects to the ticket print page after submission.
 *
 * @WebformHandler(
 *   id = "ticket_print",
 *   label = @Translation("Print ticket"),
 *   category = @Translation("Ticket"),
 *   description = @Translation("Redirects to the ticket print page after submission."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED
 * )
 */
class TicketPrintWebformHandler extends WebformHandlerBase {



  public function confirmForm(
    array &$form,
    FormStateInterface $form_state,
    WebformSubmissionInterface $webform_submission,
  ): void {

    // Only automatically print submissions created by an administrator. This check is already done by setting a condition in the webform handler settings, but we also check here to be safe.
    // if (
    //   !$current_user->hasPermission(
    //     'administer webform submission'
    //   )
    // ) {
    //   return;
    // }

    // Do not print drafts, incomplete submissions or updates.
    if (
      !$webform_submission->isCompleted()
      || !$this->appliesToSubmission($webform_submission)
      || !$webform_submission->id()
    ) {
      return;
    }

    $request = \Drupal::request();

    if (!$request->hasSession()) {
      return;
    }

    $submission_id = (int) $webform_submission->id();

    // Store the SID for the print-dialog logic.
    $request->getSession()->set(
      'webform_ticket_pdf.print_submission',
      $submission_id
    );

    $webform_id = $webform_submission->getWebform()->id();

    // Redirect to the results overview page with a query parameter to trigger printing.
    $url = Url::fromRoute(
      'entity.webform.results_submissions',
      [
        'webform' => $webform_id,
      ],
      [
        'query' => [
          'print_submission' => $submission_id,
        ],
      ]
    );

    $form_state->setResponse(
      new RedirectResponse($url->toString())
    );

  }


  /**
   * Checks whether this handler applies to the submission.
   */
  protected function appliesToSubmission(WebformSubmissionInterface $webform_submission): bool {
    return function_exists('webform_ticket_pdf_is_ticket_submission')
      && webform_ticket_pdf_is_ticket_submission($webform_submission);
  }

}
