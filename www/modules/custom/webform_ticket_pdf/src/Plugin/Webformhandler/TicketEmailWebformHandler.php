<?php

namespace Drupal\webform_ticket_pdf\Plugin\WebformHandler;

use Drupal\webform\Plugin\WebformHandler\EmailWebformHandler;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Sends a Webform email with the generated ticket PDF attached.
 *
 * @WebformHandler(
 *   id = "ticket_email",
 *   label = @Translation("Ticket email"),
 *   category = @Translation("Notification"),
 *   description = @Translation("Sends a Webform email and attaches the generated ticket PDF."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class TicketEmailWebformHandler extends EmailWebformHandler {

  /**
   * {@inheritdoc}
   */
  protected function getMessageAttachments(WebformSubmissionInterface $webform_submission) {
    $attachments = parent::getMessageAttachments($webform_submission);

    $configured_webform_id = \Drupal::service('domain_settings.manager')
      ->getTicketWebformId();

    if (!$configured_webform_id || $webform_submission->getWebform()->id() !== $configured_webform_id) {
      return $attachments;
    }

    $pdf_uri = \Drupal::service('webform_ticket_pdf.generator')
      ->generate($webform_submission);

    $pdf_path = \Drupal::service('file_system')->realpath($pdf_uri);

    if (!$pdf_path || !file_exists($pdf_path) || !is_readable($pdf_path)) {
      \Drupal::logger('webform_ticket_pdf')->error('Ticket PDF could not be attached. File missing or unreadable: @uri', [
        '@uri' => $pdf_uri,
      ]);

      return $attachments;
    }

    $webform_id = $webform_submission->getWebform()->id();
    $sid = $webform_submission->id();

    $attachments[] = [
      'filepath' => $pdf_path,
      'filename' => $webform_id . '-ticket-' . $sid . '.pdf',
      'filemime' => 'application/pdf',
    ];

    \Drupal::logger('webform_ticket_pdf')->notice('Ticket PDF added via getMessageAttachments(): @path', [
      '@path' => $pdf_path,
    ]);

    return $attachments;
  }

}
