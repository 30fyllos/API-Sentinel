<?php

namespace Drupal\api_sentinel\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Drupal\user\UserInterface;

/**
 * Event that is dispatched when a user logs in.
 */
class UserLoginEvent extends Event {

  const LOGIN = 'api_sentinel.user_login';

  /**
   * The logged-in user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user;

  /**
   * Constructs a UserLoginEvent.
   *
   * @param \Drupal\user\UserInterface $user
   *   The logged-in user.
   */
  public function __construct(UserInterface $user) {
    $this->user = $user;
  }

  /**
   * Gets the logged-in user.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity.
   */
  public function getUser(): UserInterface
  {
    return $this->user;
  }
}
