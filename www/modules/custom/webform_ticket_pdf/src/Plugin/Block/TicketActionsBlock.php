<?php

namespace Drupal\webform_ticket_pdf\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides ticket action buttons on Webform submission pages.
 *
 * @Block(
 *   id = "ticket_actions_block",
 *   admin_label = @Translation("Ticket actions")
 * )
 */
class TicketActionsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $submission = \Drupal::routeMatch()->getParameter('webform_submission');

    if (!$submission instanceof WebformSubmissionInterface) {
      return [];
    }

    $configured_webform_ids = \Drupal::service('domain_settings.manager')
      ->getDomainTicketWebformIds();

    if (!$configured_webform_ids || !in_array($submission->getWebform()->id(), $configured_webform_ids)) {
      return [];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['ticket-actions'],
      ],
      'download_ticket' => [
        '#type' => 'link',
        '#title' => $this->t('View ticket'),
        '#url' => Url::fromRoute('webform_ticket_pdf.download_ticket', [
          'webform' => $submission->getWebform()->id(),
          'webform_submission' => $submission->id(),
        ]),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
          'target' => '_blank',
        ],
      ],
      'email_ticket' => [
        '#type' => 'link',
        '#title' => $this->t('Email ticket'),
        '#url' => Url::fromRoute('webform_ticket_pdf.email_ticket', [
          'webform' => $submission->getWebform()->id(),
          'webform_submission' => $submission->id(),
        ]),
        '#attributes' => [
          'class' => ['button'],
        ],
      ],
      '#cache' => [
        'contexts' => ['route'],
        'max-age' => 0,
      ],
    ];
  }

}
