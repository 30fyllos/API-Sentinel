<?php

namespace Drupal\api_sentinel\EventSubscriber;

use Drupal\api_sentinel\Event\EntityCreateEvent;
use Drupal\api_sentinel\Event\UserLoginEvent;
use Drupal\api_sentinel\Service\ApiKeyManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\entity\Event\EntityEvents;
use Drupal\user\Event\UserEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to user entity insert events to auto-generate API keys.
 *
 * When a new user is registered, this subscriber checks the auto-generation
 * configuration. If auto-generation is enabled and the new user has one of the
 * selected roles, an API key is generated for that user.
 */
class UserAutoGenerateSubscriber implements EventSubscriberInterface {

  /**
   * The API Key Manager service.
   *
   * @var \Drupal\api_sentinel\Service\ApiKeyManagerInterface
   */
  protected $apiKeyManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new UserAutoGenerateSubscriber.
   *
   * @param \Drupal\api_sentinel\Service\ApiKeyManagerInterface $apiKeyManager
   *   The API Key Manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(ApiKeyManagerInterface $apiKeyManager, ConfigFactoryInterface $configFactory, LoggerChannelInterface $logger) {
    $this->apiKeyManager = $apiKeyManager;
    $this->configFactory = $configFactory;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   *
   * In Drupal 11, the constant EntityEvents::INSERT may not exist.
   * Therefore, we subscribe directly to the 'entity.insert' event.
   */
  public static function getSubscribedEvents() {
    return [
      EntityCreateEvent::INSERT => 'onEntityCreate',
//      UserLoginEvent::LOGIN => 'onUserLogin',
    ];
  }

  /**
   * subscribe to the user login event Dispatched.
   */
  public function onUserLogin(UserLoginEvent $event): void
  {

    $database = \Drupal::database();
    $dateFormatter = \Drupal::service('date.formatter');
    dd($event->getUser());
    $account_created = $database->select('users_field_data', 'ud')
      ->fields('ud', ['created'])
      ->condition('ud.uid', $event->account->id())
      ->execute()
      ->fetchField();

    \Drupal::messenger()->addStatus(t('Welcome, Your account was created on %created_date.', [
      '%created_date' => $dateFormatter->format($account_created, 'short'),
    ]));

  }

  /**
   * Responds to a new user entity being inserted.
   *
   * @param EntityCreateEvent $event
   *   The event triggered on entity insertion.
   */
  public function onEntityCreate(EntityCreateEvent $event): void
  {
    $entity = $event->getEntity();
    // Only act on user entities.
    if ($entity->getEntityTypeId() !== 'user') {
      return;
    }

    // Load auto-generation settings.
    $config = $this->configFactory->get('api_sentinel.settings');
    if (!$config->get('auto_generate_enabled')) {
      return;
    }

    // Get the list of roles eligible for auto-generation.
    $autoRoles = $config->get('auto_generate_roles') ?: [];
    if (empty($autoRoles)) {
      return;
    }

    // Check if the new user has any role from the auto-generation list.
    $user_roles = $entity->getRoles();
    $matched_roles = array_intersect($user_roles, $autoRoles);
    if (empty($matched_roles)) {
      return;
    }

    // Determine the expiration timestamp if provided.
    $duration = $config->get('auto_generate_duration');
    $unit = $config->get('auto_generate_duration_unit');
    $expires = $duration ? strtotime( "+ {$duration} {$unit}") : NULL;

    // Generate an API key for the new user.
    try {
      $this->apiKeyManager->generateApiKey($entity, $expires);
      $this->logger->info('Auto-generated API key for user ID @uid.', ['@uid' => $entity->id()]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to auto-generate API key for user ID @uid: @message', [
        '@uid' => $entity->id(),
        '@message' => $e->getMessage(),
      ]);
    }
  }

}
