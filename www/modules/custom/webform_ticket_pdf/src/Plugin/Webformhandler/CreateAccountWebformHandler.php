<?php

namespace Drupal\webform_ticket_pdf\Plugin\WebformHandler;

use Drupal\Component\Utility\Crypt;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Creates a Drupal account and processes Mailchimp consent.
 *
 * @WebformHandler(
 *   id = "create_account_mailchimp",
 *   label = @Translation("Create account and subscribe to Mailchimp"),
 *   category = @Translation("Registration"),
 *   description = @Translation("Creates an account from a completed submission and subscribes it to the audience for the current domain and language when consent was given."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED
 * )
 */
class CreateAccountWebformHandler extends WebformHandlerBase {

  /**
   * Creates or updates the account before the submission is saved.
   *
   * Creating the user during preSave() lets us assign the new account as the
   * owner of the Webform submission.
   */
  public function preSave(
    WebformSubmissionInterface $webform_submission,
  ): void {
    if (!$webform_submission->isCompleted()) {
      return;
    }

    $data = $webform_submission->getData();

    $email = strtolower(trim((string) ($data['e_mail'] ?? '')));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      \Drupal::logger('webform_ticket_pdf')->warning(
        'Account creation skipped for submission @sid because no valid email address was found.',
        [
          '@sid' => $webform_submission->id() ?: 'new',
        ],
      );
      return;
    }

    // An existing account must not be modified.
    if ($this->loadAccountByEmail($email)) {
      return;
    }

    $langcode = $this->getSubmissionLanguage(
      $webform_submission,
      $data,
    );

    $domain_id = $this->getSubmissionDomainId(
      $webform_submission,
    );

    $account = $this->createAccount(
      $email,
      $langcode,
      $data,
      $domain_id,
    );

    if (!$account) {
      return;
    }

    // Subscribe only when explicit newsletter consent was given.
    if ($this->hasMailchimpConsent($data)) {
      $this->subscribeAccount(
        $account,
        $domain_id,
        $langcode,
      );
    }

    try {
      $account->save();

      $webform_submission->setOwnerId((int) $account->id());

      $this->sendAccountEmail($account);
    }
    catch (\Throwable $exception) {
      \Drupal::logger('webform_ticket_pdf')->error(
        'Could not save account for @mail: @message',
        [
          '@mail' => $email,
          '@message' => $exception->getMessage(),
        ],
      );
    }
  }

  /**
   * Loads an existing user by e-mail address.
   */
  protected function loadAccountByEmail(string $email): ?UserInterface {
    $accounts = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties([
        'mail' => $email,
      ]);

    $account = reset($accounts);

    return $account instanceof UserInterface ? $account : NULL;
  }

  /**
   * Creates a new active Drupal account.
   */
  protected function createAccount(
    string $email,
    string $langcode,
    array $data,
    string $domain_id,
  ): ?UserInterface {
    try {
      $values = [
        'name' => $email,
        'mail' => $email,
        'status' => 1,
        'pass' => Crypt::randomBytesBase64(32),
        'langcode' => $langcode,
        'preferred_langcode' => $langcode,
        'preferred_admin_langcode' => $langcode,
      ];

      $account = User::create($values);

      $this->copyProfileValues($account, $data);

      if ($account->hasField('field_domain_access') && $domain_id !== '') {
        $account->set('field_domain_access', [
          ['target_id' => $domain_id],
        ]);
      }

      return $account;
    }
    catch (\Throwable $exception) {
      \Drupal::logger('webform_ticket_pdf')->error(
        'Could not create an account for @mail: @message',
        [
          '@mail' => $email,
          '@message' => $exception->getMessage(),
        ],
      );

      return NULL;
    }
  }

  /**
   * Fills empty profile fields on an existing account.
   */
  protected function updateExistingAccount(
    UserInterface $account,
    array $data,
    string $domain_id,
  ): void {
    $this->copyProfileValues($account, $data, TRUE);

    if ($account->hasField('field_domain_access') && $domain_id !== '') {
      $this->addDomainAccess($account, $domain_id);
    }
  }

  /**
   * Copies Webform values to Drupal user fields.
   */
  protected function copyProfileValues(
    UserInterface $account,
    array $data,
    bool $only_when_empty = FALSE,
  ): void {
    $mapping = [
      'first_name' => 'field_first_name',
      'last_name' => 'field_name',
      'company_name' => 'field_company_name',
    ];

    foreach ($mapping as $webform_key => $user_field) {
      if (!$account->hasField($user_field)) {
        continue;
      }

      $value = trim((string) ($data[$webform_key] ?? ''));

      if ($value === '') {
        continue;
      }

      if ($only_when_empty && !$account->get($user_field)->isEmpty()) {
        continue;
      }

      $account->set($user_field, $value);
    }
  }

  /**
   * Returns whether newsletter consent was explicitly checked.
   */
  protected function hasMailchimpConsent(array $data): bool {
    $value = $data['mailchimp_consent'] ?? FALSE;

    return $value === TRUE
      || $value === 1
      || $value === '1';
  }

  /**
   * Subscribes using the Mailchimp field already configured on the account.
   */
  protected function subscribeAccount(
    UserInterface $account,
    string $domain_id,
    string $langcode,
  ): void {
    if ($domain_id === '' || $langcode === '') {
      \Drupal::logger('webform_ticket_pdf')->warning(
        'Mailchimp subscription skipped because the domain ID or language is empty.',
      );
      return;
    }

    // Examples:
    // field_mailchimp_aquarama_nl
    // field_mailchimp_aquarama_fr
    // field_mailchimp_fm_nl
    $field_name = sprintf(
      'field_mailchimp_%s_%s',
      $this->cleanMachineName($domain_id),
      $this->cleanMachineName($langcode),
    );

    if (!$account->hasField($field_name)) {
      \Drupal::logger('webform_ticket_pdf')->warning(
        'Mailchimp subscription field @field does not exist for domain @domain and language @language.',
        [
          '@field' => $field_name,
          '@domain' => $domain_id,
          '@language' => $langcode,
        ],
      );
      return;
    }

    /*
     * "allow_unsubscribe" is a runtime value used by mailchimp_lists.
     * Without it, saving the entity only updates merge fields and does not
     * change the actual subscription status.
     */
    $account->set($field_name, [
      'subscribe' => 1,
      'allow_unsubscribe' => TRUE,
    ]);
  }

  /**
   * Adds the active domain without removing existing domain assignments.
   */
  protected function addDomainAccess(
    UserInterface $account,
    string $domain_id,
  ): void {
    $existing = array_column(
      $account->get('field_domain_access')->getValue(),
      'target_id',
    );

    if (!in_array($domain_id, $existing, TRUE)) {
      $account->get('field_domain_access')->appendItem([
        'target_id' => $domain_id,
      ]);
    }
  }

  /**
   * Gets the configured domain ID for the submission's Webform.
   */
  protected function getSubmissionDomainId(
    WebformSubmissionInterface $webform_submission,
  ): string {
    $webform_id = $webform_submission->getWebform()->id();

    $domain_id = \Drupal::service('domain_settings.manager')
      ->getDomainIdForWebform($webform_id);

    if (!$domain_id) {
      \Drupal::logger('webform_ticket_pdf')->warning(
        'No domain settings were found for Webform @webform.',
        [
          '@webform' => $webform_id,
        ],
      );

      return '';
    }

    return $domain_id;
  }

  /**
   * Determines the language in which the form was completed.
   */
  protected function getSubmissionLanguage(
    WebformSubmissionInterface $webform_submission,
    array $data,
  ): string {
    // The bulk Webforms have an explicit "language" element.
    $langcode = trim((string) ($data['language'] ?? ''));

    if ($langcode === '') {
      $langcode = $webform_submission->language()->getId();
    }

    if ($langcode === '') {
      $langcode = \Drupal::languageManager()
        ->getCurrentLanguage()
        ->getId();
    }

    // Convert values such as en-gb to en for the field-name convention.
    return strtolower(substr($langcode, 0, 2));
  }

  /**
   * Makes a value safe to use as part of a Drupal field name.
   */
  protected function cleanMachineName(string $value): string {
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9_]+/', '_', $value);

    return trim((string) $value, '_');
  }

  /**
   * Sends Drupal's one-time account login message.
   */
  protected function sendAccountEmail(UserInterface $account): void {
    try {
      _user_mail_notify('register_no_approval_required', $account);
    }
    catch (\Throwable $exception) {
      \Drupal::logger('webform_ticket_pdf')->error(
        'Account @uid was created, but its account e-mail could not be sent: @message',
        [
          '@uid' => $account->id(),
          '@message' => $exception->getMessage(),
        ],
      );
    }
  }

}
