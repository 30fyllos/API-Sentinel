<?php

namespace Drupal\api_sentinel\Authentication;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Drupal\api_sentinel\Service\ApiKeyManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Cache\CacheBackendInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides API Sentinel authentication via API keys with rate limiting.
 *
 * @AuthenticationProvider(
 *   id = "api_sentinel_auth",
 *   label = @Translation("API Sentinel Authentication"),
 *   description = @Translation("Authenticates users via API keys with rate limiting.")
 * )
 */
class ApiSentinelAuthProvider implements AuthenticationProviderInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The current path service.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected CurrentPathStack $currentPath;

  /**
   * The API key manager service.
   *
   * @var \Drupal\api_sentinel\Service\ApiKeyManagerInterface
   */
  protected ApiKeyManagerInterface $apiKeyManager;

  /**
   * Precompiled allowed path regex patterns.
   *
   * @var array
   */
  protected array $allowedPathRegex = [];

  /**
   * Constructs a new ApiSentinelAuthProvider.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\Core\Path\CurrentPathStack $currentPath
   *   The current path service.
   * @param \Drupal\api_sentinel\Service\ApiKeyManagerInterface $apiKeyManager
   *   The API key manager service.
   */
  public function __construct(Connection $database, CacheBackendInterface $cache, LoggerInterface $logger, ConfigFactoryInterface $configFactory, CurrentPathStack $currentPath, ApiKeyManagerInterface $apiKeyManager) {
    $this->database = $database;
    $this->cache = $cache;
    $this->logger = $logger;
    $this->configFactory = $configFactory;
    $this->currentPath = $currentPath;
    $this->apiKeyManager = $apiKeyManager;

    // Precompile allowed paths into regex patterns.
    $config = $this->configFactory->get('api_sentinel.settings');
    $allowedPaths = $config->get('allowed_paths') ?? [];
    foreach ($allowedPaths as $pattern) {
      // Convert wildcards (*) into regex equivalent (.*) and anchor the pattern.
      $regexPattern = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
      $this->allowedPathRegex[] = $regexPattern;
    }
  }

  /**
   * {@inheritdoc}
   *
   * Determines if this provider applies to the current request.
   */
  public function applies(Request $request): bool {
    $config = $this->configFactory->get('api_sentinel.settings');
    $customHeader = $config->get('custom_auth_header');
    // Check if an API key is provided via header or query parameter.
    return $request->headers->has($customHeader) || $request->query->has('api_key');
  }

  /**
   * {@inheritdoc}
   *
   * Authenticates the user based on an API key with rate limiting.
   *
   * @return \Drupal\Core\Session\AccountInterface|null
   *   The authenticated user, or NULL if authentication fails.
   */
  public function authenticate(Request $request): ?AccountInterface {
    $config = $this->configFactory->get('api_sentinel.settings');
    $clientIp = $request->getClientIp();
    $currentPath = $this->currentPath->getPath();

    // Retrieve IP filtering and allowed path settings.
    $whitelist = $config->get('whitelist_ips') ?? [];
    $blacklist = $config->get('blacklist_ips') ?? [];
    $customHeader = $config->get('custom_auth_header');

    // Block if the client's IP is blacklisted.
    if (in_array($clientIp, $blacklist)) {
      $this->logger->warning('Access denied: IP {ip} is blacklisted.', ['ip' => $clientIp]);
      return NULL;
    }

    // If a whitelist is set, block any IP not in the whitelist.
    if (!empty($whitelist) && !in_array($clientIp, $whitelist)) {
      $this->logger->warning('Access denied: IP {ip} is not whitelisted.', ['ip' => $clientIp]);
      return NULL;
    }

    // Check if the current path is allowed.
    $allowed = empty($this->allowedPathRegex);
    foreach ($this->allowedPathRegex as $regex) {
      if (preg_match($regex, $currentPath)) {
        $allowed = TRUE;
        break;
      }
    }
    if (!$allowed) {
      $this->logger->warning('Access denied: Path {path} is not allowed.', ['path' => $currentPath]);
      return NULL;
    }

    // Retrieve the API key from the header or query parameter.
    $apiKey = $request->headers->get($customHeader, $request->query->get('api_key'));
    if (!$apiKey) {
      $this->logger->warning('Authentication failed: No API key provided.');
      return NULL;
    }

    // Lookup the API key record.
    $record = $this->database->select('api_sentinel_keys', 'ask')
      ->fields('ask', ['id', 'uid', 'expires', 'blocked'])
      ->condition('ask.api_key', hash('sha256', $apiKey))
      ->execute()
      ->fetchAssoc();

    if (!$record || empty($record['uid'])) {
      $this->logger->warning('Authentication failed: Invalid API key.');
      return NULL;
    }

    // Check if the API key is blocked.
    if ($record['blocked']) {
      $this->apiKeyManager->logKeyUsage($record['id']);
      $this->logger->warning('Blocked API key {id} attempted authentication.', ['id' => $record['id']]);
      return NULL;
    }

    // Check for key expiration.
    if ($record['expires'] && time() > $record['expires']) {
      $this->apiKeyManager->logKeyUsage($record['id']);
      $this->logger->warning('API key for user {uid} has expired.', ['uid' => $record['uid']]);
      return NULL;
    }

    // Enforce rate limiting and failure blocking.
    if ($this->apiKeyManager->blockFailedAttempt($record['id']) || $this->apiKeyManager->checkRateLimit($record['id'])) {
      return NULL;
    }

    // Load the user entity and verify that the user is active.
    $user = User::load($record['uid']);
    if ($user && $user->isActive()) {
      $this->apiKeyManager->logKeyUsage($record['id'], TRUE);
      $this->logger->info('User {uid} authenticated successfully via API key.', ['uid' => $record['uid']]);
      return $user;
    }

    $this->apiKeyManager->logKeyUsage($record['id']);
    $this->logger->warning('Authentication failed: User ID {uid} is not active.', ['uid' => $record['uid']]);
    return NULL;
  }
}
