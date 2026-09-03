<?php

namespace Drupal\domain_settings\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Entity\Webform;

class DomainSettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'domain_settings_form';
  }

  protected function getEditableConfigNames(): array {
    return [
      'domain_settings.settings',
    ];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('domain_settings.settings');
    $domains = $config->get('domains') ?: [];

    $webform_options = [
      '' => $this->t('- None -'),
    ];

    $webforms = Webform::loadMultiple();

    foreach ($webforms as $webform) {
      $webform_options[$webform->id()] = $webform->label() . ' (' . $webform->id() . ')';
    }

    asort($webform_options);

    $form['description'] = [
      '#markup' => '<p>Configure general settings per domain. These values can be reused by other modules, tokens, and Webform email handlers.</p>',
    ];

    $form['domains'] = [
      '#type' => 'table',
      '#header' => [
        ['data' => $this->t('Domain'), 'class' => ['col-domain']],
        ['data' => $this->t('Domain ID'), 'class' => ['col-domain-id']],
        ['data' => $this->t('Email address'), 'class' => ['col-email']],
        ['data' => $this->t('Ticket Webform'), 'class' => ['col-webform']],
        ['data' => $this->t('Ticket Exhibitor Webform'), 'class' => ['col-webform']],
        ['data' => $this->t('Ticket Visitor Bulk Webform'), 'class' => ['col-webform']],
        ['data' => $this->t('Ticket template'), 'class' => ['col-template']],
        ['data' => $this->t('Lead Retrieval URL'), 'class' => ['col-url']],
      ],
      '#attributes' => [
        'class' => ['domain-settings-table'],
      ],
      '#prefix' => '<div class="domain-settings-table-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
      '#empty' => $this->t('No domain settings configured.'),
    ];

    $form['#attached']['library'][] = 'domain_settings/admin';

    /*
     * Show existing rows plus a few empty rows for adding new domains.
     * Increase this number if you want more empty rows.
     */
    $row_count = max(count($domains), 4);

    for ($i = 0; $i < $row_count; $i++) {
      $row = $domains[$i] ?? [];

      $form['domains'][$i]['domain'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Domain'),
        '#title_display' => 'invisible',
        '#default_value' => $row['domain'] ?? '',
        '#placeholder' => 'example.com',
        '#size' => 35,
      ];

      $form['domains'][$i]['domain_id'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Domain ID'),
        '#title_display' => 'invisible',
        '#default_value' => $row['domain_id'] ?? '',
        '#placeholder' => 'Domain ID',
        '#size' => 35,
      ];

      $form['domains'][$i]['mail'] = [
        '#type' => 'email',
        '#title' => $this->t('Email address'),
        '#title_display' => 'invisible',
        '#default_value' => $row['mail'] ?? '',
        '#placeholder' => 'info@example.com',
        '#size' => 35,
      ];

      $form['domains'][$i]['ticket_webform_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Ticket Webform'),
        '#title_display' => 'invisible',
        '#options' => $webform_options,
        '#default_value' => $row['ticket_webform_id'] ?? '',
      ];

      $form['domains'][$i]['ticket_exhibitor_webform_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Ticket Exhibitor Webform'),
        '#title_display' => 'invisible',
        '#options' => $webform_options,
        '#default_value' => $row['ticket_exhibitor_webform_id'] ?? '',
      ];

      $form['domains'][$i]['ticket_visitor_bulk_webform_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Ticket Visitor Bulk Webform'),
        '#title_display' => 'invisible',
        '#options' => $webform_options,
        '#default_value' => $row['ticket_visitor_bulk_webform_id'] ?? '',
      ];

      $form['domains'][$i]['ticket_template'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Ticket template'),
        '#title_display' => 'invisible',
        '#default_value' => $row['ticket_template'] ?? '',
        '#placeholder' => 'private://templates/<magazine>/<year>/<event>',
        '#size' => 45,
      ];

      $form['domains'][$i]['lead_retrieval_url'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Lead Retrieval URL'),
        '#title_display' => 'invisible',
        '#default_value' => $row['lead_retrieval_url'] ?? '',
        '#placeholder' => 'https://<event><year>-events.fcoffice.be/apps/lr/',
        '#size' => 45,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $domains = $form_state->getValue('domains') ?: [];
    $seen = [];

    foreach ($domains as $index => $row) {
      $domain = strtolower(trim($row['domain'] ?? ''));
      $domain_id = trim($row['domain_id'] ?? '');
      $mail = trim($row['mail'] ?? '');
      $ticket_webform_id = trim($row['ticket_webform_id'] ?? '');
      $ticket_exhibitor_webform_id = trim($row['ticket_exhibitor_webform_id'] ?? '');
      $ticket_visitor_bulk_webform_id = trim($row['ticket_visitor_bulk_webform_id'] ?? '');
      $ticket_template = trim($row['ticket_template'] ?? '');
      $lead_retrieval_url = trim($row['lead_retrieval_url'] ?? '');

      // Ignore completely empty rows.
      if ($domain === '' && $domain_id === '' && $mail === '' && $ticket_webform_id === '' && $ticket_exhibitor_webform_id === '' && $ticket_visitor_bulk_webform_id === '' && $ticket_template === '' && $lead_retrieval_url === '') {
        continue;
      }

      // If a row is partly filled, domain is required.
      if ($domain === '') {
        $form_state->setErrorByName("domains][$index][domain", $this->t('Domain is required.'));
      }

      // Email is optional, but if present it must be valid.
      // The #type email element validates format automatically.

      if ($domain !== '') {
        if (isset($seen[$domain])) {
          $form_state->setErrorByName("domains][$index][domain", $this->t('Duplicate domain: @domain', [
            '@domain' => $domain,
          ]));
        }

        $seen[$domain] = TRUE;
      }
    }

    parent::validateForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $domains = $form_state->getValue('domains') ?: [];
    $clean_domains = [];

    foreach ($domains as $row) {
      $domain = strtolower(trim($row['domain'] ?? ''));
      $domain_id = trim($row['domain_id'] ?? '');
      $mail = trim($row['mail'] ?? '');
      $ticket_webform_id = trim($row['ticket_webform_id'] ?? '');
      $ticket_exhibitor_webform_id = trim($row['ticket_exhibitor_webform_id'] ?? '');
      $ticket_visitor_bulk_webform_id = trim($row['ticket_visitor_bulk_webform_id'] ?? '');
      $ticket_template = trim($row['ticket_template'] ?? '');
      $lead_retrieval_url = trim($row['lead_retrieval_url'] ?? '');

      // Ignore empty rows.
      if ($domain === '' && $domain_id === '' && $mail === '' && $ticket_webform_id === '' && $ticket_exhibitor_webform_id === ''  && $ticket_visitor_bulk_webform_id === '' && $ticket_template === '' && $lead_retrieval_url === '') {
        continue;
      }

      $clean_domains[] = [
        'domain' => $domain,
        'domain_id' => $domain_id,
        'mail' => $mail,
        'ticket_webform_id' => $ticket_webform_id,
        'ticket_exhibitor_webform_id' => $ticket_exhibitor_webform_id,
        'ticket_visitor_bulk_webform_id' => $ticket_visitor_bulk_webform_id,
        'ticket_template' => $ticket_template,
        'lead_retrieval_url' => $lead_retrieval_url,
      ];
    }

    $this->config('domain_settings.settings')
      ->set('domains', $clean_domains)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
