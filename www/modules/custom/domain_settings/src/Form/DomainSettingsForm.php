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
        $this->t('Domain'),
        $this->t('Email address'),
        $this->t('Ticket Webform'),
        $this->t('Ticket template'),
      ],
      '#tree' => TRUE,
      '#empty' => $this->t('No domain settings configured.'),
    ];

    /*
     * Show existing rows plus a few empty rows for adding new domains.
     * Increase this number if you want more empty rows.
     */
    $row_count = max(count($domains) + 5, 10);

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

      $form['domains'][$i]['ticket_template'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Ticket template'),
        '#title_display' => 'invisible',
        '#default_value' => $row['ticket_template'] ?? '',
        '#placeholder' => 'private://templates/<magazine>/<year>/<event>',
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
      $mail = trim($row['mail'] ?? '');
      $ticket_webform_id = trim($row['ticket_webform_id'] ?? '');
      $ticket_template = trim($row['ticket_template'] ?? '');

      // Ignore completely empty rows.
      if ($domain === '' && $mail === '' && $ticket_webform_id === '' && $ticket_template === '') {
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
      $mail = trim($row['mail'] ?? '');
      $ticket_webform_id = trim($row['ticket_webform_id'] ?? '');
      $ticket_template = trim($row['ticket_template'] ?? '');

      // Ignore empty rows.
      if ($domain === '' && $mail === '' && $ticket_webform_id === '' && $ticket_template === '') {
        continue;
      }

      $clean_domains[] = [
        'domain' => $domain,
        'mail' => $mail,
        'ticket_webform_id' => $ticket_webform_id,
        'ticket_template' => $ticket_template
      ];
    }

    $this->config('domain_settings.settings')
      ->set('domains', $clean_domains)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
