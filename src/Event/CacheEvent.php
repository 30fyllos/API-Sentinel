<?php

namespace Drupal\api_sentinel\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event that is dispatched when a new entity is created.
 */
class CacheEvent extends Event {

  /**
   * Event name for entity creation.
   */
  const FLUSH = 'api_sentinel.cache_flush';
}
