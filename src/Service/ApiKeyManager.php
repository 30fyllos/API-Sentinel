<?php

namespace Drupal\api_sentinel\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
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
   * The logger service.
   *
   * @var LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs the API Key Manager service.
   *
   * @param Connection $database
   *   The database connection.
   * @param ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param LoggerInterface $logger
   *    The logger service.
   */
  public function __construct(Connection $database, ConfigFactoryInterface $configFactory, LoggerInterface $logger) {
    $this->database = $database;
    $this->configFactory = $configFactory;
    $this->logger = $logger;
  }

  /**
   * Logs API key changes.
   */
  private function logKeyChange($uid, $message): void
  {
    $this->logger->info($message, [
      'uid' => $uid,
      'changed_by' => \Drupal::currentUser()->id(),
    ]);
  }

  /**
   * Encrypts a value using AES-256.
   * @throws RandomException
   */
  private function encryptValue($value): string
  {
    $config = $this->configFactory->get('api_sentinel.settings');
    $encryptionKey = $config->get('encryption_key');

    if (!$encryptionKey || strlen($encryptionKey) !== 32) {
      throw new \Exception('Encryption key is invalid.');
    }

    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($value, 'aes-256-cbc', $encryptionKey, 0, $iv);
    return base64_encode($iv . $encrypted);
  }

  /**
   * Decrypts a value using AES-256.
   */
  private function decryptValue($encryptedValue): false|string
  {
    $config = $this->configFactory->get('api_sentinel.settings');
    $encryptionKey = $config->get('encryption_key');

    if (!$encryptionKey || strlen($encryptionKey) !== 32) {
      return 'Error: Invalid encryption key.';
    }

    $data = base64_decode($encryptedValue);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);

    return openssl_decrypt($encrypted, 'aes-256-cbc', $encryptionKey, 0, $iv);
  }

  /**
   * Generates a new API key for a user.
   *
   * @param AccountInterface $account
   *   The user account.
   * @param int|null $expires
   *   Expiration timestamp.
   * @return string
   *   The generated API key.
   * @throws RandomException
   */
  public function generateApiKey(AccountInterface $account, int $expires = NULL): string
  {
    $apiKey = base64_encode(random_bytes(32));
    $config = $this->configFactory->get('api_sentinel.settings');
    $useEncryption = $config->get('use_encryption');
    $encryptionKey = $config->get('encryption_key');

    // Store hashed or plaintext based on settings
    if ($useEncryption && !empty($encryptionKey)) {
      $storedKey = $this->encryptValue($apiKey);
    } else {
      $storedKey = hash('sha256', $apiKey);
    }

    // Store hashed key in the database.
    $this->database->merge('api_sentinel_keys')
      ->key('uid', $account->id())
      ->fields([
        'api_key' => $storedKey,
        'created' => time(),
        'expires' => $expires,
      ])
      ->execute();

    $this->logKeyChange($account->id(), 'Generated a new API key.');
    return $apiKey; // Return raw key for user storage.
  }

  /**
   * Forces regeneration of all API keys.
   * @throws RandomException
   */
  public function forceRegenerateAllKeys(): void
  {
    $storedKeys = $this->database->select('api_sentinel_keys', 'ask')
      ->fields('ask', ['uid', 'expires'])
      ->execute()
      ->fetchCol();

    $this->logger->warning('All API keys have been regenerated due to encryption key change.', [
      'changed_by' => \Drupal::currentUser()->id(),
    ]);

    foreach ($storedKeys as $storedKey) {
      $this->generateApiKey($storedKey->uid, $storedKey->expires);
    }
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

    $this->logKeyChange($account->id(), 'Revoked API key.');
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
    $expires = $this->apiKeyExpiration($account);

    // Delete the old key.
    $this->revokeApiKey($account);

    // Generate a new key.
    return $this->generateApiKey($account, $expires);
  }

  /**
   * Checks if a user has an API key.
   *
   * @param AccountInterface|int $account
   *   The user account or id.
   *
   * @return bool
   *   TRUE if an API key exists, FALSE otherwise.
   */
  public function hasApiKey(AccountInterface|int $account): bool
  {
    if ($account instanceof AccountInterface) {
      $account = $account->id();
    }

    $query = $this->database->select('api_sentinel_keys', 'ask')
      ->fields('ask', ['api_key'])
      ->condition('ask.uid', $account)
      ->execute()
      ->fetchField();

    return !empty($query);
  }

  /**
   * User Key expiration date.
   *
   * @param AccountInterface|int $account
   *    The user account or id.
   *
   * @return int|null
   *   Api Key expiration date.
   */
  public function apiKeyExpiration(AccountInterface|int $account): null|int
  {
    if ($account instanceof AccountInterface) {
      $account = $account->id();
    }

    return $this->database->select('api_sentinel_keys', 'ask')
      ->fields('ask', ['expires'])
      ->condition('ask.uid', $account)
      ->execute()
      ->fetchField();
  }

  /**
   * Log API key usage.
   *
   * @param int $id
   *   The id of the key.
   * @param bool $status
   *   The status success or failed.
   *
   * @return void
   * @throws \Exception
   */
  public function logKeyUsage(int $id, bool $status = FALSE): void
  {
    $this->database->insert('api_sentinel_usage')
      ->fields([
        'key_id' => $id,
        'used_at' => time(),
        'status' => $status ? 1 : 0,
      ])
      ->execute();
  }

  /**
   * Generates API keys for all users who don’t have one, filtered by role.
   *
   * @param array $roles
   *   An array of role IDs to filter users. If empty, all users are included.
   * @param int|null $expires
   *    Expiration timestamp.
   *
   * @return int
   *   The number of API keys generated.
   * @throws RandomException
   */
  public function generateApiKeysForAllUsers(array $roles = [], int $expires = NULL): int
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
      if (!$this->hasApiKey($uid)) {
        $user = User::load($uid);
        if ($user && $user->isActive()) {
          $this->generateApiKey($user, $expires);
          $generatedCount++;
        }
      }
    }

    return $generatedCount;
  }

}
