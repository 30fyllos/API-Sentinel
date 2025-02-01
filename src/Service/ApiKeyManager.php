<?php

namespace Drupal\api_sentinel\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Random\RandomException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Service for managing API keys in API Sentinel.
 */
class ApiKeyManager {

  /**
   * The database connection.
   *
   * @var Connection
   */
  protected Connection $database;

  /**
   * The configuration factory.
   *
   * @var ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs the API Key Manager service.
   *
   * @param Connection $database
   *   The database connection.
   * @param ConfigFactoryInterface $configFactory
   *   The configuration factory.
   */
  public function __construct(Connection $database, ConfigFactoryInterface $configFactory) {
    $this->database = $database;
    $this->configFactory = $configFactory;
  }

  /**
   * Generates a new API key for a user.
   *
   * @param AccountInterface $account
   *   The user account.
   *
   * @return string
   *   The generated API key.
   * @throws RandomException
   */
  public function generateApiKey(AccountInterface $account): string
  {
    $apiKey = bin2hex(random_bytes(32)); // 64-character secure key.

    // Store hashed key in the database.
    $this->database->merge('api_sentinel_keys')
      ->key('uid', $account->id())
      ->fields([
        'api_key' => hash('sha256', $apiKey), // Store securely.
        'created' => time(),
      ])
      ->execute();

    return $apiKey; // Return raw key for user storage.
  }

  /**
   * Revokes a user's API key.
   *
   * @param AccountInterface $account
   *   The user account.
   */
  public function revokeApiKey(AccountInterface $account): void
  {
    $this->database->delete('api_sentinel_keys')
      ->condition('uid', $account->id())
      ->execute();
  }

  /**
   * Regenerates an API key for a user.
   *
   * @param AccountInterface $account
   *   The user account.
   *
   * @return string
   *   The new API key.
   * @throws RandomException
   */
  public function regenerateApiKey(AccountInterface $account): string
  {
    // Delete the old key.
    $this->revokeApiKey($account);

    // Generate a new key.
    return $this->generateApiKey($account);
  }

  /**
   * Checks if a user has an API key.
   *
   * @param AccountInterface $account
   *   The user account.
   *
   * @return bool
   *   TRUE if an API key exists, FALSE otherwise.
   */
  public function hasApiKey(AccountInterface $account): bool
  {
    $query = $this->database->select('api_sentinel_keys', 'ask')
      ->fields('ask', ['api_key'])
      ->condition('ask.uid', $account->id())
      ->execute()
      ->fetchField();

    return !empty($query);
  }

  /**
   * Generates API keys for all users who don’t have one, filtered by role.
   *
   * @param array $roles
   *   An array of role IDs to filter users. If empty, all users are included.
   *
   * @return int
   *   The number of API keys generated.
   */
  public function generateApiKeysForAllUsers(array $roles = []): int
  {
    if (empty($roles)) return 0;

    $query = $this->database->select('users_field_data', 'u')
      ->fields('u', ['uid'])
      ->condition('u.uid', 1, '>') // Exclude anonymous user.
      ->condition('u.status', 1); // Exclude blocked users.

    if (!in_array('authenticated', $roles)) {
      $query->leftJoin('user__roles', 'ur', 'u.uid = ur.entity_id');
      $query->condition('ur.roles_target_id', $roles, 'IN');
    }

    $users = $query->execute()->fetchCol();
    $generatedCount = 0;

    foreach ($users as $uid) {
      $hasKey = $this->database->select('api_sentinel_keys', 'ask')
        ->condition('uid', $uid)
        ->countQuery()
        ->execute()
        ->fetchField();

      if (!$hasKey) {
        $user = User::load($uid);
        if ($user && $user->isActive()) {
          $this->generateApiKey($user);
          $generatedCount++;
        }
      }
    }

    return $generatedCount;
  }

}
