<?php

namespace Drupal\api_sentinel\Service;

use Drupal\api_sentinel\Enum\Timeframe;
use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Random\RandomException;

/**
 * Class ApiKeyManager.
 *
 * Provides methods to manage API keys including encryption, generation,
 * revocation, rate limiting, and usage logging.
 */
class ApiKeyManager implements ApiKeyManagerInterface {

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
   * The cache backend.
   *
   * @var CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * The logger service.
   *
   * @var LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The current user service.
   *
   * @var AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The temp store factory.
   *
   * @var PrivateTempStoreFactory
   */
  protected PrivateTempStoreFactory $tempStoreFactory;

  /**
   * The notification service.
   *
   * @var ApiSentinelNotificationServiceInterface
   */
  protected ApiSentinelNotificationServiceInterface $notificationService;

  /**
   * Cached configuration settings.
   *
   * @var array
   */
  protected array $settings = [];

  /**
   * Constructs a new ApiKeyManager.
   *
   * @param Connection $database
   *   The database connection.
   * @param ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param LoggerInterface $logger
   *   The logger service.
   * @param CacheBackendInterface $cache
   *   The cache backend.
   * @param AccountProxyInterface $currentUser
   *   The current user.
   * @param PrivateTempStoreFactory $tempStoreFactory
   *   The temp store factory.
   * @param ApiSentinelNotificationServiceInterface $notificationService
   *   The service for sending API key notifications
   */
  public function __construct(Connection $database, ConfigFactoryInterface $configFactory, LoggerInterface $logger, CacheBackendInterface $cache, AccountProxyInterface $currentUser, PrivateTempStoreFactory $tempStoreFactory, ApiSentinelNotificationServiceInterface $notificationService) {
    $this->database = $database;
    $this->configFactory = $configFactory;
    $this->logger = $logger;
    $this->cache = $cache;
    $this->currentUser = $currentUser;
    $this->tempStoreFactory = $tempStoreFactory;
    $this->notificationService = $notificationService;
  }

  /**
   * Initializes the configuration settings if not already set.
   */
  protected function initSettings(): void {
    if (empty($this->settings)) {
      $this->settings = $this->configFactory->get('api_sentinel.settings')->getRawData();
    }
  }

  /**
   * Logs API key changes.
   *
   * @param int $uid
   *   The user ID.
   * @param string $message
   *   The log message.
   */
  protected function logKeyChange(int $uid, string $message): void {
    $this->logger->info($message, [
      'uid' => $uid,
      'changed_by' => $this->currentUser->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvValue(): string
  {
    $env_path = dirname(DRUPAL_ROOT) . '/.env';

    if (file_exists($env_path)) {
      $env_values = parse_ini_file($env_path);
      return $env_values['API_SENTINEL_ENCRYPTION_KEY'] ?? '';
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function encryptValue(string $value): string {
    $this->initSettings();
    $encryptionKey = $this->getEnvValue() ?? $this->settings['encryption_key'];

    if (!$encryptionKey) {
      throw new \Exception('Encryption key is invalid.');
    }
    // Use a random IV for better security.
    $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($value, 'aes-256-cbc', $encryptionKey, 0, $iv);
    // Prepend IV to the encrypted value.
    return base64_encode($iv . $encrypted);
  }

  /**
   * {@inheritdoc}
   */
  public function decryptValue(string $encryptedValue): false|string
  {
    $this->initSettings();
    $encryptionKey = $this->getEnvValue() ?? $this->settings['encryption_key'];
    if (!$encryptionKey) {
      return 'Error: Invalid encryption key.';
    }
    $data = base64_decode($encryptedValue);
    $ivLength = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);
    return openssl_decrypt($encrypted, 'aes-256-cbc', $encryptionKey, 0, $iv);
  }

  /**
   * {@inheritdoc}
   * @throws \Exception
   */
  public function generateApiKey(AccountInterface $account, ?int $expires = NULL): void
  {
    try {
      $apiKey = base64_encode(random_bytes(32));
    }
    catch (\Exception $e) {
      $this->logger->error('Error generating API key: @message', ['@message' => $e->getMessage()]);
      throw $e;
    }

    // Merge (insert or update) the API key record.
    $this->database->merge('api_sentinel_keys')
      ->key('uid', $account->id())
      ->fields([
        'api_key' => hash('sha256', $apiKey),
        'data' => $this->encryptValue($apiKey),
        'created' => time(),
        'expires' => $expires,
      ])
      ->execute();

    $this->logKeyChange($account->id(), 'Generated a new API key.');
    $this->notificationService->notifyNewKey($account);
  }

  /**
   * {@inheritdoc}
   */
  public function forceRegenerateAllKeys(): int
  {
    $storedKeys = $this->database->select('api_sentinel_keys', 'ask')
      ->fields('ask', ['uid', 'expires'])
      ->execute()
      ->fetchAll();
    $this->logger->warning('All API keys have been regenerated due to encryption key change.', [
      'changed_by' => $this->currentUser->id(),
    ]);
    $count = 0;
    foreach ($storedKeys as $storedKey) {
      $user = User::load($storedKey->uid);
      if ($user) {
        $this->generateApiKey($user, $storedKey->expires);
        $count++;
      }
    }
    return $count;
  }

  /**
   * {@inheritdoc}
   */
  public function revokeApiKey(AccountInterface $account): void {
    $this->database->delete('api_sentinel_keys')
      ->condition('uid', $account->id())
      ->execute();
    $this->logKeyChange($account->id(), 'Revoked API key.');
  }

  /**
   * {@inheritdoc}
   */
  public function regenerateApiKey(AccountInterface $account): void {
    $expires = $this->apiKeyExpiration($account);
    // Revoke the old key.
    $this->revokeApiKey($account);
    // Generate a new key.
    $this->generateApiKey($account, $expires);
  }

  /**
   * {@inheritdoc}
   */
  public function hasApiKey(AccountInterface|string $account): int|null {
    if ($account instanceof AccountInterface) {
      $account = $account->id();
    }
    $keyId = $this->database->select('api_sentinel_keys', 'ask')
      ->fields('ask', ['id'])
      ->condition('ask.uid', $account)
      ->execute()
      ->fetchField();

    return $keyId ? (int) $keyId : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function matchApiKey(AccountInterface|string $account, $keyId): ?int
  {
    if ($account instanceof AccountInterface) {
      $account = $account->id();
    }
    $uid = $this->database->select('api_sentinel_keys', 'ask')
      ->fields('ask', ['uid'])
      ->condition('ask.uid', $account)
      ->condition('ask.id', $keyId)
      ->execute()
      ->fetchField();

    return $uid ? (int) $uid : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getApiKeyStatus(int $key_id): ?int {
    return $this->database->select('api_sentinel_keys', 'ask')
      ->fields('ask', ['blocked'])
      ->condition('id', $key_id)
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function toggleApiKeyStatus(int $key_id): bool {
    $current_status = $this->getApiKeyStatus($key_id);

    if ($current_status === NULL) {
      return FALSE;
    }

    $new_status = $current_status ? 0 : 1;

    $this->database->update('api_sentinel_keys')
      ->fields(['blocked' => $new_status])
      ->condition('id', $key_id)
      ->execute();

    // Log the change.
    $message = $new_status ? 'API key has been blocked.' : 'API key has been unblocked.';
    $this->logger->notice($message, ['key_id' => $key_id, 'changed_by' => $this->currentUser->id()]);

    return TRUE;
  }


  /**
   * {@inheritdoc}
   */
  public function apiKeyExpiration(AccountInterface|string $account): ?int {
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
   * {@inheritdoc}
   */
  public function logKeyUsage(int $keyId, bool $status = FALSE): void {
    $this->database->insert('api_sentinel_usage')
      ->fields([
        'key_id' => $keyId,
        'used_at' => time(),
        'status' => $status ? 1 : 0,
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function checkRateLimit(int $keyId): bool {
    $this->initSettings();
    $maxRateLimit = $this->settings['max_rate_limit'] ?? 0;
    if ($maxRateLimit > 0) {
      $timeThreshold = Timeframe::fromString($this->settings['max_rate_limit_time'] ?? '1h')?->toTimestamp();
      $cache_id = "api_sentinel:usage:{$keyId}";
      if ($cache_item = $this->cache->get($cache_id)) {
        $requestCount = $cache_item->data;
      }
      else {
        $requestCount = (int) $this->database->select('api_sentinel_usage', 'asu')
          ->condition('key_id', $keyId)
          ->condition('used_at', $timeThreshold, '>')
          ->countQuery()
          ->execute()
          ->fetchField();
        // Cache the count for 60 seconds.
        $this->cache->set($cache_id, $requestCount, time() + 60);
      }
      if ($requestCount >= $maxRateLimit) {
        $this->logger->warning('API key {id} exceeded rate limit.', ['id' => $keyId]);
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function blockFailedAttempt(int $keyId): bool {
    $this->initSettings();
    $failureLimit = $this->settings['failure_limit'] ?? 0;
    if ($failureLimit > 0) {
      $cache_id = "api_sentinel:failures:{$keyId}";
      $failureCount = ($this->cache->get($cache_id)) ? $this->cache->get($cache_id)->data : 0;
      $failureCount++;
      // Set the cache TTL based on a failure limit time; here we default to 1 hour.
      $failureTtl = strtotime('+1 hour') - time();
      $this->cache->set($cache_id, $failureCount, time() + $failureTtl);
      if ($failureCount >= $failureLimit) {
        $this->database->update('api_sentinel_keys')
          ->fields(['blocked' => 1])
          ->condition('id', $keyId)
          ->execute();
        $this->logger->notice('API key {id} has been blocked after {failures} failed attempts.', [
          'id' => $keyId,
          'failures' => $failureCount,
        ]);
        $this->cache->delete($cache_id);
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function generateApiKeysForAllUsers(array $roles = [], ?int $expires = NULL): int
  {
    if (empty($roles)) {
      return 0;
    }
    $query = $this->database->select('users_field_data', 'u')
      ->fields('u', ['uid'])
      ->condition('u.uid', 1, '>') // Exclude anonymous user.
      ->condition('u.status', 1);   // Only active users.
    // If not all authenticated users, join roles and filter.
    if (!in_array('authenticated', $roles)) {
      $query->leftJoin('user__roles', 'ur', 'u.uid = ur.entity_id');
      $query->condition('ur.roles_target_id', $roles, 'IN');
    }
    $users = $query->execute()->fetchCol();
    $count = 0;
    foreach ($users as $uid) {
      if (!$this->hasApiKey($uid)) {
        $user = User::load($uid);
        if ($user && $user->isActive()) {
          $this->generateApiKey($user, $expires);
          $count++;
        }
      }
    }
    return $count;
  }

  /**
   * {@inheritdoc}
   */
  public function apiKeyUsageLast(int $keyId, string $timeCondition = '-1 hour'): mixed
  {
    return $this->database->select('api_sentinel_usage', 'asu')
      ->condition('asu.key_id', $keyId)
      ->condition('asu.used_at', strtotime($timeCondition), '>')
      ->countQuery()
      ->execute()
      ->fetchField();
  }
}
