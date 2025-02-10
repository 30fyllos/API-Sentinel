<?php

namespace Drupal\api_sentinel\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Class UserAccessCheck
 *
 * Provides custom access control for routes with a user ID parameter.
 *
 * @package Drupal\my_module\Access
 */
class UserAccessCheck implements AccessInterface {

  /**
   * Checks access for a given route based on the user ID.
   *
   * This function ensures that only the user matching the `uid` parameter
   * can access the specified route.
   *
   * @param AccountInterface $account
   *   The currently logged-in user.
   * @param Route $route
   *   The route being checked for access.
   * @param int $uid
   *   The user ID (uid) parameter from the route.
   *
   * @return AccessResult
   *   Returns an AccessResult object indicating whether access is allowed.
   */
  public function access(AccountInterface $account, Route $route, int $uid): AccessResult
  {
    // Check if the current user's ID matches the requested uid.
    if ($account->id() == $uid) {
      return AccessResult::allowed();
    }

    // Deny access if the IDs do not match.
    return AccessResult::forbidden();
  }

}
