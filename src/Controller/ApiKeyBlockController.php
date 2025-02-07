<?php

namespace Drupal\api_sentinel\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

/**
 * Controller for blocking and unblocking API keys.
 */
class ApiKeyBlockController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new API Key Block Controller.
   */
  public function __construct(Connection $database, MessengerInterface $messenger) {
    $this->database = $database;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('messenger')
    );
  }

  /**
   * Toggle the block status of an API key.
   */
  public function toggleBlockStatus($key_id) {
    $query = $this->database->select('api_sentinel_keys', 'ask')
      ->fields('ask', ['blocked'])
      ->condition('id', $key_id)
      ->execute()
      ->fetchAssoc();

    if (!$query) {
      $this->messenger->addError($this->t('API key not found.'));
      return new RedirectResponse(Url::fromRoute('api_sentinel.dashboard')->toString());
    }

    $newStatus = $query['blocked'] ? 0 : 1;
    $this->database->update('api_sentinel_keys')
      ->fields(['blocked' => $newStatus])
      ->condition('id', $key_id)
      ->execute();

    $message = $newStatus ? $this->t('API key has been blocked.') : $this->t('API key has been unblocked.');
    $this->messenger->addStatus($message);

    return new RedirectResponse(Url::fromRoute('api_sentinel.dashboard')->toString());
  }
}
