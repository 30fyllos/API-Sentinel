<?php

namespace Drupal\api_sentinel\Service;

use Drupal\Core\Session\AccountInterface;
use Random\RandomException;

/**
 * Interface for the API Key Manager service.
 *
 * Provides methods for encrypting values, generating and revoking API keys,
 * tracking usage and rate limits, and more.
 */
interface ApiKeyManagerInterface {

  /**
   * Load an environment variable from .env file.
   *
   * @return string
   *    The env value.
   *
   */
  public function getEnvValue(): string;

  /**
   * Encrypts a value using AES-256.
   *
   * @param string $value
   *   The value to encrypt.
   *
   * @return string
   *   The encrypted value.
   *
   * @throws \Exception
   */
  public function encryptValue(string $value): string;

  /**
   * Decrypts a value using AES-256.
   *
   * @param string $encryptedValue
   *   The encrypted value.
   *
   * @return false|string
   *   The decrypted value, or FALSE on failure.
   */
  public function decryptValue(string $encryptedValue): false|string;

  /**
   * Generates a new API key for a user.
   *
   * @param AccountInterface $account
   *   The user account.
   * @param int|null $expires
   *   (Optional) Expiration timestamp.
   *
   * @return void
   *   The generated API key.
   *
   * @throws RandomException
   */
  public function generateApiKey(AccountInterface $account, ?int $expires = NULL): void;

  /**
   * Forces regeneration of all API keys.
   *
   * @return int
   *
   * @throws RandomException
   */
  public function forceRegenerateAllKeys(): int;

  /**
   * Revokes a user's API key.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return void
   */
  public function revokeApiKey(AccountInterface $account): void;

  /**
   * Regenerates an API key for a user.
   *
   * @param AccountInterface $account
   *   The user account.
   *
   * @return void
   *   The new API key.
   *
   * @throws RandomException
   */
  public function regenerateApiKey(AccountInterface $account): void;

  /**
   * Checks if a user has an API key.
   *
   * @param \Drupal\Core\Session\AccountInterface|string $account
   *   The user account or user ID.
   *
   * @return bool
   *   TRUE if an API key exists, FALSE otherwise.
   */
  public function hasApiKey(AccountInterface|string $account): bool;

  /**
   * Gets the API key expiration timestamp for a user.
   *
   * @param AccountInterface|string $account
   *   The user account.
   *
   * @return int|null
   *   The expiration timestamp or NULL if not set.
   */
  public function apiKeyExpiration(AccountInterface|string $account): ?int;

  /**
   * Logs API key usage.
   *
   * @param int $keyId
   *   The API key ID.
   * @param bool $status
   *   The status (TRUE for success, FALSE for failure).
   *
   * @return void
   */
  public function logKeyUsage(int $keyId, bool $status = FALSE): void;

  /**
   * Checks if the API key has exceeded the rate limit.
   *
   * @param int $keyId
   *   The API key ID.
   *
   * @return bool
   *   TRUE if the rate limit is exceeded, FALSE otherwise.
   */
  public function checkRateLimit(int $keyId): bool;

  /**
   * Blocks an API key after too many failed attempts.
   *
   * @param int $keyId
   *   The API key ID.
   *
   * @return bool
   *   TRUE if the key has been blocked, FALSE otherwise.
   */
  public function blockFailedAttempt(int $keyId): bool;

  /**
   * Generates API keys for all users without one, optionally filtered by roles.
   *
   * @param array $roles
   *   An array of role IDs. If empty, no filtering is applied.
   * @param int|null $expires
   *   (Optional) Expiration timestamp.
   *
   * @return int
   *   The number of API keys generated.
   *
   * @throws RandomException
   */
  public function generateApiKeysForAllUsers(array $roles = [], ?int $expires = NULL): int;

  /**
   * Gets the number of times the API key has been used within a specified period.
   *
   * @param int $keyId
   *   The API key ID.
   * @param string $timeCondition
   *   The time condition (e.g. '-1 hour').
   *
   * @return mixed
   *   The number of times used.
   */
  public function apiKeyUsageLast(int $keyId, string $timeCondition = '-1 hour'): mixed;
}
