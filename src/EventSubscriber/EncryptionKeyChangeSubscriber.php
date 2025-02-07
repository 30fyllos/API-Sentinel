<?php

namespace Drupal\api_sentinel\EventSubscriber;

use Drupal\api_sentinel\Event\CacheEvent;
use Drupal\api_sentinel\Service\ApiKeyManagerInterface;
use Random\RandomException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Psr\Log\LoggerInterface;

/**
 * Detects changes in the API encryption key and updates its hash.
 */
class EncryptionKeyChangeSubscriber implements EventSubscriberInterface {

  /**
   * The config factory service.
   *
   * @var ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The state service.
   *
   * @var StateInterface
   */
  protected StateInterface $state;

  /**
   * The logger service.
   *
   * @var LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The API key manager service.
   *
   * @var ApiKeyManagerInterface
   */
  protected ApiKeyManagerInterface $apiKeyManager;

  /**
   * Constructs the subscriber.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state, LoggerInterface $logger, ApiKeyManagerInterface $apiKeyManager) {
    $this->configFactory = $config_factory;
    $this->state = $state;
    $this->logger = $logger;
    $this->apiKeyManager = $apiKeyManager;
  }

  /**
   * Runs once per cache clear or per session.
   * @throws RandomException
   */
  public function onFlushCache(): void
  {

    // Hash the key using SHA-256.
    $hashed_env_key = hash('sha256', $this->apiKeyManager->getEnvValue());

    // Load the stored encryption key hash from configuration.
    $config = $this->configFactory->getEditable('api_sentinel.settings');
    $stored_hashed_key = $config->get('encryption_key_hash');

    // Compare the hashed keys.
    if ($hashed_env_key !== $stored_hashed_key) {
      // Force key regeneration.
      $this->apiKeyManager->forceRegenerateAllKeys();

      // Log the change.
      $this->logger->warning('API encryption key has changed. Updating the stored hash.');

      // Update the stored hash in configuration.
      $config->set('encryption_key_hash', $hashed_env_key)->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array
  {
    return [
      CacheEvent::FLUSH => ['onFlushCache', 50],
    ];
  }
}
