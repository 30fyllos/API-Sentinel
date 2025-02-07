<?php

namespace Drupal\api_sentinel\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\api_sentinel\Service\ApiSentinelNotificationService;

/**
 * Processes API Sentinel notification tasks.
 *
 * @QueueWorker(
 *   id = "api_sentinel_notification",
 *   title = @Translation("API Sentinel Notification Queue Worker"),
 *   cron = {"time" = 30}
 * )
 */
class ApiSentinelNotificationQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The API Sentinel Notification service.
   *
   * @var \Drupal\api_sentinel\Service\ApiSentinelNotificationService
   */
  protected ApiSentinelNotificationService $notificationService;

  /**
   * Constructs a new ApiSentinelNotificationQueueWorker.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\api_sentinel\Service\ApiSentinelNotificationService $notificationService
   *   The API Sentinel Notification service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ApiSentinelNotificationService $notificationService) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->notificationService = $notificationService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('api_sentinel.notification')
    );
  }

  /**
   * Processes a single queue item.
   *
   * @param mixed $data
   *   The data for the queue item.
   */
  public function processItem($data) {
    // Process the notification using our service.
    $this->notificationService->processNotification($data);
  }

  /**
   * {@inheritdoc}
   */
  public function getProgress() {
    // Optionally implement progress reporting.
    return NULL;
  }

}
