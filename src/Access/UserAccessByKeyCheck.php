<?php

namespace Drupal\api_sentinel\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Class UserAccessCheck
 *
 * Provides custom access control for routes.
 *
 * @package Drupal\api_sentinel\Access
 */
class UserAccessByKeyCheck implements AccessInterface {

  /**
   * Checks access for a given route based on the Key ID.
   *
   * @param AccountInterface $account
   *   The currently logged-in user.
   * @param Route $route
   *   The route being checked for access.
   * @param int $key_id
   *   The Key ID parameter from the route.
   *
   * @return AccessResult
   *   Returns an AccessResult object indicating whether access is allowed.
   */
  public function access(AccountInterface $account, Route $route, int $key_id): AccessResult
  {
    // Allow admins
    if ($account->hasPermission('administer api keys')) {
      return AccessResult::allowed();
    }
    // Check if the key ID matches the requested user.
    if ($account->id() == \Drupal::service('api_sentinel.api_key_manager')->matchApiKey($account, $key_id)) {
      return AccessResult::allowed();
    }

    // Deny access if the IDs do not match.
    return AccessResult::forbidden();
  }

}
