<?php

namespace Drupal\api_sentinel\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Psr\Log\LoggerInterface;

/**
 * Logs API requests for auditing.
 */
class ApiSentinelEventSubscriber implements EventSubscriberInterface {

  /**
   * The logger service.
   *
   * @var LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs an API Sentinel event subscriber.
   *
   * @param LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *    The configuration factory.
   */
  public function __construct(LoggerInterface $logger, ConfigFactoryInterface $configFactory) {
    $this->logger = $logger;
    $this->configFactory = $configFactory;
  }

  /**
   * Logs all incoming API requests.
   *
   * @param RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event): void
  {
    $config = $this->configFactory->get('api_sentinel.settings');
    $request = $event->getRequest();
    $customHeader = $config->get('custom_auth_header');
    if ($request->headers->has($customHeader)) {
      $path = $request->getPathInfo();
      $ip = $request->getClientIp();
      // Log the API request.
      $this->logger->info("API request received: $path from IP $ip.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array
  {
    return [
      KernelEvents::REQUEST => ['onRequest', 10],
    ];
  }
}
