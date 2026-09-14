<?php

namespace Drupal\webform_ticket_pdf\Drush\Commands;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\Entity\User;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drupal\webform_ticket_pdf\TicketPdfGenerator;

final class PeopleImportCommands extends DrushCommands {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TicketPdfGenerator $ticketPdfGenerator,
  ) {
    parent::__construct();
  }

  #[CLI\Command(name: 'webform-ticket-pdf:import-people', aliases: ['wtp:import'])]
  #[CLI\Argument(name: 'file', description: 'Absolute path to the CSV file.')]
  #[CLI\Argument(name: 'webform_id', description: 'The webform machine name.')]
  #[CLI\Option(name: 'delimiter', description: 'CSV delimiter.')]
  #[CLI\Usage(
    name: 'drush wtp:import /var/www/html/private/people.csv fm_day_2026_bulk',
    description: 'Import accounts and webform submissions.',
  )]
  public function import(
    string $file,
    string $webform_id,
    array $options = ['delimiter' => ','],
  ): void {

    $csv_path = realpath($file);

    if ($csv_path === FALSE || !is_file($csv_path)) {
      throw new \RuntimeException("CSV file does not exist: $file");
    }

    $import_directory = dirname($csv_path);

    if (!is_writable($import_directory)) {
      throw new \RuntimeException(
        "CSV directory is not writable: $import_directory"
      );
    }

    if (!is_readable($file)) {
      throw new \RuntimeException("CSV file is not readable: $file");
    }

    $webform = Webform::load($webform_id);

    if (!$webform) {
      throw new \RuntimeException("Webform '$webform_id' does not exist.");
    }

    $delimiter = (string) ($options['delimiter'] ?: ',');

    $handle = fopen($file, 'rb');

    if (!$handle) {
      throw new \RuntimeException("Could not open CSV file: $file");
    }

    $headers = fgetcsv($handle, separator: $delimiter);

    if (!$headers) {
      fclose($handle);
      throw new \RuntimeException('The CSV file has no header row.');
    }

    // Remove a possible UTF-8 BOM from the first heading.
    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);

    $required_headers = [
      'last_name',
      'first_name',
      'company_name',
      'e_mail',
      'language',
    ];

    $missing_headers = array_diff($required_headers, $headers);

    if ($missing_headers) {
      fclose($handle);
      throw new \RuntimeException(
        'Missing CSV columns: ' . implode(', ', $missing_headers)
      );
    }

    $created_users = 0;
    $created_submissions = 0;
    $skipped_submissions = 0;
    $invalid_rows = 0;
    $row_number = 1;

    while (($values = fgetcsv($handle, separator: $delimiter)) !== FALSE) {
      $row_number++;

      if (count($headers) !== count($values)) {
        $this->logger()->warning(
          "Row $row_number has an incorrect number of columns."
        );
        $invalid_rows++;
        continue;
      }

      $row = array_combine($headers, $values);

      $email = mb_strtolower(trim($row['e_mail'] ?? ''));

      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->logger()->warning(
          "Row $row_number has an invalid email address: $email"
        );
        $invalid_rows++;
        continue;
      }

      $language = mb_strtolower(trim($row['language'] ?? ''));

      // Use the exact enabled Drupal language codes.
      // Change 'en' to 'en-gb' if that is the configured code on your site.
      $allowed_languages = ['nl', 'fr', 'en'];

      if (!in_array($language, $allowed_languages, TRUE)) {
        $this->logger()->warning(
          "Row $row_number has an invalid language '$language'; skipped."
        );
        $invalid_rows++;
        continue;
      }

      $account = $this->loadUserByEmail($email);

      if (!$account) {
        $account = User::create([
          'name' => $this->createUniqueUsername($email),
          'mail' => $email,
          'status' => 1,
          'pass' => Crypt::randomBytesBase64(32),
        ]);

        // Add these only if the fields exist on your user entity.
        if ($account->hasField('field_first_name')) {
          $account->set('field_first_name', trim($row['first_name']));
        }

        if ($account->hasField('field_last_name')) {
          $account->set('field_last_name', trim($row['last_name']));
        }

        if ($account->hasField('field_company_name')) {
          $account->set('field_company_name', trim($row['company_name']));
        }

        $account->save();
        $created_users++;

        $this->logger()->success("Created account: $email");
      }

      if ($this->submissionExists($webform_id, (int) $account->id())) {
        $this->logger()->notice(
          "Submission already exists for $email; skipped."
        );
        $skipped_submissions++;
        continue;
      }

      $submission = WebformSubmission::create([
        'webform_id' => $webform_id,
        'uid' => $account->id(),
        'langcode' => $language,
        'remote_addr' => '127.0.0.1',
        'data' => [
          // Replace these with the actual webform element keys.
          'last_name' => trim($row['last_name']),
          'first_name' => trim($row['first_name']),
          'company_name' => trim($row['company_name']),
          'e_mail' => $email,
          'language' => $language,
          'profile_custom' => 'member',
          'is_import' => 'true',
        ],
      ]);

      $submission->save();
      $created_submissions++;

      // $pdf_filename = sprintf(
      //   '%s-ticket-%d.pdf',
      //   $webform_id,
      //   $submission->id(),
      // );

      // $pdf_path = $import_directory . DIRECTORY_SEPARATOR . $pdf_filename;

      // try {
      //   $this->ticketPdfGenerator->save($submission, $pdf_path);

      //   $this->logger()->success(
      //     "Created submission {$submission->id()} and PDF $pdf_filename for $email"
      //   );
      // }
      // catch (\Throwable $exception) {
      //   $this->logger()->error(
      //     "Submission {$submission->id()} was created, but its PDF failed: {$exception->getMessage()}"
      //   );
      // }


      $this->logger()->success(
        "Created submission {$submission->id()} for $email"
      );
    }

    fclose($handle);

    $this->io()->success(sprintf(
      'Finished: %d accounts created, %d submissions created, %d existing submissions skipped, %d invalid rows.',
      $created_users,
      $created_submissions,
      $skipped_submissions,
      $invalid_rows,
    ));
  }

  private function loadUserByEmail(string $email): ?User {
    $users = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties(['mail' => $email]);

    $account = reset($users);

    return $account instanceof User ? $account : NULL;
  }

  private function submissionExists(string $webform_id, int $uid): bool {
    $ids = $this->entityTypeManager
      ->getStorage('webform_submission')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('webform_id', $webform_id)
      ->condition('uid', $uid)
      ->range(0, 1)
      ->execute();

    return !empty($ids);
  }

  private function createUniqueUsername(string $email): string {
    $base = $email;
    $username = $base;
    $counter = 1;

    while ($this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties(['name' => $username])) {
      $username = $base . '-' . $counter++;
    }

    return $username;
  }

}
