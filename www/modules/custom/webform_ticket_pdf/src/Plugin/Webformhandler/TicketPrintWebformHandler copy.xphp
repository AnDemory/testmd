<?php

namespace Drupal\webform_ticket_pdf\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\Plugin\WebformHandlerInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Redirects completed submissions to the ticket print page.
 *
 * @WebformHandler(
 *   id = "ticket_print",
 *   label = @Translation("Print ticket"),
 *   category = @Translation("Ticket"),
 *   description = @Translation("Opens the ticket PDF and displays the browser print dialog after submission."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED
 * )
 */
class TicketPrintWebformHandler extends WebformHandlerBase {

//   /**
//    * {@inheritdoc}
//    */
//   public function confirmForm(
//     array &$form,
//     FormStateInterface $form_state,
//     WebformSubmissionInterface $webform_submission,
//   ): void {
//     \Drupal::logger('TicketPrintWebformHandler')->notice('TicketPrintWebformHandler confirmForm called for submission ID: ' . $webform_submission->id());
//     if (!$this->appliesToSubmission($webform_submission)) {
//       return;
//     }
// \Drupal::logger('TicketPrintWebformHandler')->notice('TicketPrintWebformHandler applies to submission ID: ' . $webform_submission->id());
//     if (!$webform_submission->id()) {
//       return;
//     }
// \Drupal::logger('TicketPrintWebformHandler')->notice('TicketPrintWebformHandler go to');
//     $form_state->setRedirectUrl(
//       Url::fromRoute('webform_ticket_pdf.print_ticket', [
//         'webform_submission' => $webform_submission->id(),
//       ])
//     );
//   }

  /**
   * Checks whether this handler applies to the submission.
   */
  protected function appliesToSubmission(
    WebformSubmissionInterface $webform_submission,
  ): bool {
    return function_exists('webform_ticket_pdf_is_ticket_submission')
      && webform_ticket_pdf_is_ticket_submission($webform_submission);
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(): array {
    return [
      'action' => [
        '#markup' => '<div><strong>' .
          $this->t('Action') .
          ':</strong> ' .
          $this->t('Open the generated ticket and display the print dialog.') .
          '</div>',
      ],
    ];
  }

}
