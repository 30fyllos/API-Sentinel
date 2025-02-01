<?php

namespace Drupal\api_sentinel\EventSubscriber;

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
   * Constructs an API Sentinel event subscriber.
   *
   * @param LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * Logs all incoming API requests.
   *
   * @param RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event): void
  {
    $request = $event->getRequest();
    $path = $request->getPathInfo();
    $ip = $request->getClientIp();

    // Log the API request.
    $this->logger->info("API request received: $path from IP $ip.");
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
