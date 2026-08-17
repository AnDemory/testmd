<?php

namespace Drupal\webform_ticket_pdf\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Drupal\webform\WebformSubmissionInterface;

class WebformAdminThemeNegotiator implements ThemeNegotiatorInterface {

  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match): bool {
    // Only administrators / Webform managers.
    if (!$this->currentUser->hasPermission('administer webform submission')) {
      return FALSE;
    }

    // A submission entity is present on submission edit/view routes.
    $submission = $route_match->getParameter('webform_submission');

    if ($submission instanceof WebformSubmissionInterface) {
      return TRUE;
    }

    // Also handle Webform routes where we only have the Webform entity.
    $webform = $route_match->getParameter('webform');

    if ($webform && str_starts_with($route_match->getRouteName(), 'entity.webform')) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match): ?string {
    return $this->configFactory
      ->get('system.theme')
      ->get('admin');
  }

}