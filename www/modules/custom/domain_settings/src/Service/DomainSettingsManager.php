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
  public function getTicketWebformId(?string $domain = NULL): ?string {
    $settings = $this->getForDomain($domain);

    return $settings['ticket_webform_id'] ?? NULL;
  }

  /**
   * Returns configured ticket template URI for a domain.
   */
  public function getTicketTemplate(?string $domain = NULL): ?string {
    $settings = $this->getForDomain($domain);

    return $settings['ticket_template'] ?? NULL;
  }

}
