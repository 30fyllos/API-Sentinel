<?php

namespace Drupal\api_sentinel\Service;

use Drupal\Core\Session\AccountInterface;

/**
 * Interface for the API Sentinel Notification Service.
 *
 * This service is responsible for notifying users via email and on-site
 * messages for various API key events (new key generation, block/unblock,
 * revocation, rate limit reached, etc.). It also supports queuing notifications
 * for asynchronous processing.
 */
interface ApiSentinelNotificationServiceInterface {

  /**
   * Queues a notification message.
   *
   * @param string $type
   *   The type of notification. Possible values: 'new_key', 'block', 'unblock',
   *   'revoke', 'rate_limit', etc.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to notify.
   * @param array $data
   *   (Optional) Additional data relevant to the notification.
   */
  public function queueNotification(string $type, AccountInterface $account, array $data = []): void;

  /**
   * Notifies a user of a new API key generation.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   */
  public function notifyNewKey(AccountInterface $account): void;

  /**
   * Notifies a user when their API key has been blocked.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   */
  public function notifyBlocked(AccountInterface $account): void;

  /**
   * Notifies a user when their API key has been unblocked.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   */
  public function notifyUnblocked(AccountInterface $account): void;

  /**
   * Notifies a user when their API key has been revoked.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   */
  public function notifyRevoked(AccountInterface $account): void;

  /**
   * Notifies a user when their API key has reached its rate limit.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   */
  public function notifyRateLimit(AccountInterface $account): void;

  /**
   * Processes a single notification item.
   *
   * This method is intended to be called by the queue worker.
   *
   * @param array $notification
   *   The notification data from the queue.
   */
  public function processNotification(array $notification): void;
}
