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
  public function encryptValue($value): string
  {
    $config = $this->configFactory->get('api_sentinel.settings');
    $encryptionKey = $config->get('encryption_key');

    if (!$encryptionKey) {
      throw new \Exception('Encryption key is invalid.');
    }

    $iv = md5(php_uname(), true);

    $encrypted = openssl_encrypt($value, 'aes-256-cbc', $encryptionKey, 0, $iv);
    return base64_encode($iv . $encrypted);
  }

  /**
   * Decrypts a value using AES-256.
   */
  public function decryptValue($encryptedValue): false|string
  {
    $config = $this->configFactory->get('api_sentinel.settings');
    $encryptionKey = $config->get('encryption_key');

    if (!$encryptionKey) {
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
        'api_key_sample' => substr($apiKey, -6),
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
      ->fetchAll();

    $this->logger->warning('All API keys have been regenerated due to encryption key change.', [
      'changed_by' => \Drupal::currentUser()->id(),
    ]);

    foreach ($storedKeys as $storedKey) {
      $this->generateApiKey(User::load($storedKey->uid), $storedKey->expires);
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
   * @param AccountInterface|string $account
   *   The user account or id.
   *
   * @return bool
   *   TRUE if an API key exists, FALSE otherwise.
   */
  public function hasApiKey(AccountInterface|string $account): bool
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
   * @param AccountInterface $account
   *    The user account or id.
   *
   * @return int|null
   *   Api Key expiration date.
   */
  public function apiKeyExpiration(AccountInterface $account): null|int
  {
    return $this->database->select('api_sentinel_keys', 'ask')
      ->fields('ask', ['expires'])
      ->condition('ask.uid', $account->id())
      ->execute()
      ->fetchField();
  }

  /**
   * Log API key usage.
   *
   * @param int $keyId
   *   The id of the key.
   * @param bool $status
   *   The status success or failed.
   *
   * @return void
   * @throws \Exception
   */
  public function logKeyUsage(int $keyId, bool $status = FALSE): void
  {
    $this->database->insert('api_sentinel_usage')
      ->fields([
        'key_id' => $keyId,
        'used_at' => time(),
        'status' => $status ? 1 : 0,
      ])
      ->execute();
  }

  /**
   * Check rate limit.
   *
   * @param int $keyId
   *   The api key id.
   *
   * @return boolean
   *   Return true if exceeded.
   */
  public function checkRateLimit(int $keyId): bool
  {
    $config = \Drupal::config('api_sentinel.settings');
    $maxRateLimit = $config->get('max_rate_limit');
    // Check rate limit
    if ($maxRateLimit > 0) {
      $requestCount = $this->database->select('api_sentinel_usage', 'asu')
        ->condition('key_id', $keyId)
        ->condition('used_at', $this->convertTimeToTimestamp($config->get('max_rate_limit_time')), '>')
        ->countQuery()
        ->execute()
        ->fetchField();

      if ($requestCount >= $maxRateLimit) {
        \Drupal::logger('api_sentinel')->warning('API key {id} exceeded rate limit.', ['id' => $requestCount['id']]);
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Block an api key after continuously fails.
   *
   * @param int $keyId
   *   The api key id.
   *
   * @return bool
   *   Return true if blocked.
   * @throws \Exception
   */
  public function blockFailedAttempt(int $keyId): bool
  {
    $config = \Drupal::config('api_sentinel.settings');
    $failureLimit = $config->get('failure_limit');

    // Check if failure limit is reached
    if ($failureLimit > 0) {
      $this->database->insert('api_sentinel_usage')
        ->fields([
          'key_id' => $keyId,
          'used_at' => time(),
          'status' => 0,
        ])
        ->execute();

      $failureCount = $this->database->select('api_sentinel_usage', 'asu')
        ->condition('key_id', $keyId)
        ->condition('status', 0)
        ->condition('used_at', $this->convertTimeToTimestamp($config->get('failure_limit_time')), '>')
        ->countQuery()
        ->execute()
        ->fetchField();

      if ($failureCount >= $failureLimit) {
        $this->database->update('api_sentinel_keys')
          ->fields(['blocked' => 1])
          ->condition('id', $keyId)
          ->execute();

        \Drupal::logger('api_sentinel')->notice('API key {id} has been blocked after {failures} failed attempts.', [
          'id' => $keyId,
          'failures' => $failureCount,
        ]);
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Convert time to timestamp.
   *
   * @param $timeString
   *   The selected options as string.
   * @return int
   *   Return a timestamp.
   */
  public function convertTimeToTimestamp($timeString): int
  {
    return match ($timeString) {
      'half_hour' => strtotime('-30 minutes'),
      'hours_2' => strtotime('-2 hours'),
      'hours_3' => strtotime('-3 hours'),
      'hours_6' => strtotime('-6 hours'),
      'half_day' => strtotime('-12 hours'),
      'day' => strtotime('-1 day'),
      default => strtotime('-1 hour'),
    };
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

  /**
   * Api key usage.
   *
   * @param $key_id
   *   The id of the api key.
   * @param string $timeCondition
   *   The time period to count.
   * @return mixed
   *   Return the times used.
   */
  public function apiKeyUsageLast($key_id, string $timeCondition = '-1 hour'): mixed
  {
    return $this->database->select('api_sentinel_usage', 'asu')
      ->condition('asu.key_id', $key_id)
      ->condition('asu.used_at', strtotime($timeCondition), '>')
      ->countQuery()
      ->execute()
      ->fetchField();
  }
}
