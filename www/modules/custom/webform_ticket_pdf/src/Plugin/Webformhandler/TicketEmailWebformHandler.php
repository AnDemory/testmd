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
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED
 * )
 */
class TicketEmailWebformHandler extends EmailWebformHandler {

  /**
   * Sends the ticket email manually.
   *
   * This is used by the "Email me the ticket" button.
   */
  public function sendTicketEmail(WebformSubmissionInterface $webform_submission): bool {
    if (!$this->appliesToSubmission($webform_submission)) {
      return FALSE;
    }

    $message = $this->getMessage($webform_submission);

    if (!$message) {
      return FALSE;
    }

    $this->sendMessage($webform_submission, $message);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function getMessageAttachments(WebformSubmissionInterface $webform_submission) {
    $attachments = [];

    if (!$this->appliesToSubmission($webform_submission)) {
      return $attachments;
    }

    $webform_id = $webform_submission->getWebform()->id();
    $sid = $webform_submission->id();

     $domain = webform_ticket_pdf_get_domain_for_submission($webform_submission);

    if (!$domain) {
       return $attachments;
    }

    $filecontent = \Drupal::service('webform_ticket_pdf.generator')
      ->generate($webform_submission, $domain);

    if ($filecontent === FALSE || $filecontent === '') {
      return $attachments;
    }

    $attachments[] = [
      'filecontent' => $filecontent,
      'filename' => $webform_id . '-ticket-' . $sid . '.pdf',
      'filemime' => 'application/pdf',
    ];

    return $attachments;
  }

  /**
   * Checks whether this handler should apply to this submission.
   */
  protected function appliesToSubmission(WebformSubmissionInterface $webform_submission): bool {
    //   $configured_webform_id = \Drupal::service('domain_settings.manager')
    //     ->getTicketWebformId();

    //   if (!$configured_webform_id) {
    //     return FALSE;
    //   }

    //   return $webform_submission->getWebform()->id() === $configured_webform_id;
    return function_exists('webform_ticket_pdf_is_ticket_submission')
      && webform_ticket_pdf_is_ticket_submission($webform_submission);
  }

  /**
   * {@inheritdoc}
   */
  protected function supportsAttachments() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $configuration = $this->getConfiguration();

    $summary = [];

    if (!empty($configuration['settings']['to_mail'])) {
      $summary['to'] = [
        '#markup' => '<div><strong>' . $this->t('To') . ':</strong> ' . $configuration['settings']['to_mail'] . '</div>',
      ];
    }

    $from = '';

    if (!empty($configuration['settings']['from_name'])) {
      $from .= $configuration['settings']['from_name'];
    }

    if (!empty($configuration['settings']['from_mail'])) {
      $from .= $from ? ' &lt;' . $configuration['settings']['from_mail'] . '&gt;' : $configuration['settings']['from_mail'];
    }

    if ($from) {
      $summary['from'] = [
        '#markup' => '<div><strong>' . $this->t('From') . ':</strong> ' . $from . '</div>',
      ];
    }

    if (!empty($configuration['settings']['subject'])) {
      $summary['subject'] = [
        '#markup' => '<div><strong>' . $this->t('Subject') . ':</strong> ' . $configuration['settings']['subject'] . '</div>',
      ];
    }

    $settings = [];

    if (!empty($configuration['settings']['html'])) {
      $settings[] = $this->t('HTML');
    }

    if ($settings) {
      $summary['settings'] = [
        '#markup' => '<div><strong>' . $this->t('Settings') . ':</strong> ' . implode('; ', $settings) . '</div>',
      ];
    }

    $state_labels = [
      'completed' => $this->t('Completed'),
      'updated' => $this->t('Updated'),
      'deleted' => $this->t('Deleted'),
      'draft_created' => $this->t('Draft created'),
      'draft_updated' => $this->t('Draft updated'),
      'converted' => $this->t('Converted'),
    ];

    $sent_when = [];

    if (!empty($this->configuration['states'])) {
      foreach ($this->configuration['states'] as $state) {
        if (is_string($state) && $state !== '') {
          $sent_when[] = $state_labels[$state] ?? ucfirst(str_replace('_', ' ', $state));
        }
      }
    }

    if ($sent_when) {
      $summary['sent_when'] = [
        '#markup' => '<div><strong>' . $this->t('Sent when') . ':</strong> ' . implode('; ', $sent_when) . '</div>',
      ];
    }

    $summary['ticket_attachment'] = [
      '#markup' => '<div><strong>' . $this->t('Attachment') . ':</strong> ' . $this->t('Generated ticket PDF') . '</div>',
    ];

    return $summary;
  }

}
