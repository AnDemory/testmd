<?php

namespace Drupal\domain_settings\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class DomainSettingsManager {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected RequestStack $requestStack,
  ) {}

  /**
   * Returns all configured domain rows.
   */
  public function getAll(): array {
    return $this->configFactory
      ->get('domain_settings.settings')
      ->get('domains') ?: [];
  }

  /**
   * Returns the current host/domain.
   */
  public function getCurrentDomain(): string {
    $request = $this->requestStack->getCurrentRequest();

    if (!$request) {
      return '';
    }

    return strtolower($request->getHost());
  }

  /**
   * Returns settings for a specific domain, or current domain if omitted.
   */
  public function getForDomain(?string $domain = NULL): ?array {
    $domain = strtolower($domain ?: $this->getCurrentDomain());

    foreach ($this->getAll() as $settings) {
      if (strtolower($settings['domain'] ?? '') === $domain) {
        return $settings;
      }
    }

    return NULL;
  }

  /**
   * Returns configured mail for a domain.
   */
  public function getMail(?string $domain = NULL): ?string {
    $settings = $this->getForDomain($domain);

    return $settings['mail'] ?? NULL;
  }

  /**
   * Returns configured ticket Webform ID for a domain.
   */
  // public function getTicketWebformId(?string $domain = NULL): ?string {
  //   $settings = $this->getForDomain($domain);

  //   return $settings['ticket_webform_id'] ?? NULL;
  // }
  // public function getTicketWebformId(?string $domain_id = NULL): ?string {
  //   if (!$domain_id) {
  //     // $domain = $this->domainNegotiator->getActiveDomain();
  //     $domain = \Drupal::service('domain.negotiator')->getActiveDomain();

  //     if (!$domain) {
  //       return NULL;
  //     }

  //     $domain_id = $domain->id();
  //   }

  //   $settings = $this->getSettings($domain_id);

  //   return $settings['ticket_webform_id'] ?? NULL;
  // }
/**
 * Returns configured ticket Webform ID for a domain.
 */
public function getTicketWebformId(?string $domain = NULL): ?string {
  $settings = $this->getForDomain($domain);
  // \Drupal::logger('DomainSettingsManager')->notice( $domain);
  return $settings['ticket_webform_id'] ?? NULL;
}
/**
 * Returns configured ticket Webform ID for a domain.
 */
public function getTicketExhibitorWebformId(?string $domain = NULL): ?string {
  $settings = $this->getForDomain($domain);

  return $settings['ticket_exhibitor_webform_id'] ?? NULL;
}
  /**
   * Returns configured ticket template URI for a domain.
   */
  public function getTicketTemplate(?string $domain = NULL): ?string {
    $settings = $this->getForDomain($domain);

    return $settings['ticket_template'] ?? NULL;
  }

   /**
   * Returns Lead Retrieval URL for a domain.
   */
  public function getLeadRetrievalUrl(?string $domain = NULL): ?string {
    $settings = $this->getForDomain($domain);

    return $settings['lead_retrieval_url'] ?? NULL;
  }

  // public function getTicketWebformIdsByDomain(): array {
  //   $result = [];

  //   $domains = \Drupal::entityTypeManager()
  //     ->getStorage('domain')
  //     ->loadMultiple();

  //   foreach ($domains as $domain) {
  //     $domain_id = $domain->id();

  //     $ticket_webform_id = $this->getTicketWebformId($domain_id);

  //     if ($ticket_webform_id) {
  //       $result[$domain_id] = $ticket_webform_id;
  //     }
  //   }

  //   return $result;
  // }
/**
 * Returns configured ticket Webform IDs keyed by configured domain host.
 */
public function getTicketWebformIdsByDomain(): array {
  $result = [];

  foreach ($this->getAll() as $settings) {
    $domain = strtolower($settings['domain'] ?? '');
    $ticket_webform_id = $settings['ticket_webform_id'] ?? '';

    if ($domain && $ticket_webform_id) {
      $result[$domain] = $ticket_webform_id;
    }
  }

  return $result;
}
/**
 * Returns configured ticket Webform IDs keyed by configured domain host.
 */
public function getTicketExhibitorWebformIdsByDomain(): array {
  $result = [];

  foreach ($this->getAll() as $settings) {
    $domain = strtolower($settings['domain'] ?? '');
    $ticket_exhibitor_webform_id = $settings['ticket_exhibitor_webform_id'] ?? '';

    if ($domain && $ticket_exhibitor_webform_id) {
      $result[$domain] = $ticket_exhibitor_webform_id;
    }
  }

  return $result;
}
}
