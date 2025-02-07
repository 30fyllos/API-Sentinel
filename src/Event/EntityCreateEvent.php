<?php

namespace Drupal\api_sentinel\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;

/**
 * Event that is dispatched when a new entity is created.
 */
class EntityCreateEvent extends Event {

  /**
   * Event name for entity creation.
   */
  const INSERT = 'api_sentinel.entity_create';

  /**
   * The entity that was created.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $entity;

  /**
   * Constructs the event object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The created entity.
   */
  public function __construct(EntityInterface $entity) {
    $this->entity = $entity;
  }

  /**
   * Gets the entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The created entity.
   */
  public function getEntity(): EntityInterface
  {
    return $this->entity;
  }
}
