<?php

namespace Drupal\api_sentinel\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Provides an AJAX dialog to show full API keys.
 */
class ApiKeyDialogController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The configuration factory.
   *
   * @var ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new ApiKeyDialogController.
   */
  public function __construct(Connection $database, AccountProxyInterface $current_user, ConfigFactoryInterface $configFactory) {
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('current_user'),
      $container->get('config.factory')
    );
  }

  /**
   * Returns the API key in a secure AJAX dialog.
   */
  public function showApiKey($key_id) {
    $config = $this->configFactory->get('api_sentinel.settings');
    $useEncryption = $config->get('use_encryption');

    $query = $this->database->select('api_sentinel_keys', 'ask')
      ->fields('ask', ['api_key'])
      ->condition('id', $key_id)
      ->execute()
      ->fetchAssoc();

    if (!$query) {
      return new JsonResponse(['error' => $this->t('API key not found.')], 404);
    }

    $msg = $useEncryption ? '<strong>' . $this->t('Full API Key:') . '</strong><br><code>' . \Drupal::service('api_sentinel.api_key_manager')->decryptValue($query['api_key']) . '</code>' : '<strong>' . $this->t('Api key is not available') . '</strong><br>Change to encrypted API keys to have access to the keys at anytime.';

    return [
      '#type' => 'markup',
      '#markup' => $msg,
    ];
  }
}
