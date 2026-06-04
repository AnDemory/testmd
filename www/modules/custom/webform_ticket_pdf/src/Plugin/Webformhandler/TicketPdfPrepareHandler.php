<?php

namespace Drupal\webform_ticket_pdf\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Prepares a generated ticket PDF before Webform emails are sent.
 *
 * @WebformHandler(
 *   id = "ticket_pdf_prepare",
 *   label = @Translation("Prepare ticket PDF"),
 *   category = @Translation("Ticket PDF"),
 *   description = @Translation("Generates the ticket PDF before email handlers are executed."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class TicketPdfPrepareHandler extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $configured_webform_id = \Drupal::service('domain_settings.manager')
      ->getTicketWebformId();

    if (!$configured_webform_id || $webform_submission->getWebform()->id() !== $configured_webform_id) {
      return;
    }

    /** @var \Drupal\webform_ticket_pdf\TicketPdfGenerator $generator */
    $generator = \Drupal::service('webform_ticket_pdf.generator');

    $pdf_uri = $generator->generate($webform_submission);
    $pdf_path = \Drupal::service('file_system')->realpath($pdf_uri);

    if (!$pdf_path || !file_exists($pdf_path) || !is_readable($pdf_path)) {
      \Drupal::logger('webform_ticket_pdf')->error('Prepared ticket PDF is missing or unreadable: @uri', [
        '@uri' => $pdf_uri,
      ]);
      return;
    }

    $webform_id = $webform_submission->getWebform()->id();
    $sid = $webform_submission->id();
    $filename = $webform_id . '-ticket-' . $sid . '.pdf';

    $data = $webform_submission->getData();
    $data['ticket_pdf'] = $pdf_uri;
    $webform_submission->setData($data);

    /*
     * Do not call $webform_submission->save() here.
     * We only need the generated file to exist before the email handler runs.
     */

    \Drupal::state()->set('webform_ticket_pdf.attachment.' . $webform_submission->id(), [
      'uri' => $pdf_uri,
      'path' => $pdf_path,
      'filename' => $filename,
      'filemime' => 'application/pdf',
    ]);

    \Drupal::logger('webform_ticket_pdf')->notice('Prepared ticket PDF attachment before email: @filename', [
      '@filename' => $filename,
    ]);
  }

}
