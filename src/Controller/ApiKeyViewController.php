<?php

namespace Drupal\api_sentinel\Controller;

use Drupal\api_sentinel\Service\ApiKeyManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Provides an AJAX dialog to show full API keys.
 */
class ApiKeyViewController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The API Key Manager service.
   *
   * @var ApiKeyManager
   */
  protected ApiKeyManager $apiKeyManager;

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
   * Constructs a new ApiKeyViewController.
   */
  public function __construct(ApiKeyManager $apiKeyManager, Connection $database, AccountProxyInterface $current_user) {
    $this->apiKeyManager = $apiKeyManager;
    $this->database = $database;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('api_sentinel.api_key_manager'),
      $container->get('database'),
      $container->get('current_user'),
    );
  }

  /**
   * Returns the API key in a secure AJAX dialog.
   */
  public function showApiKey($uid) {
    $query = $this->database->select('api_sentinel_keys', 'ask')
      ->fields('ask', ['data', 'expires'])
      ->condition('uid', $uid)
      ->execute()
      ->fetchAssoc();

    if (!$query) {
      return new JsonResponse(['error' => $this->t('API key not found.')], 404);
    }

    $expires = $query['expires'] ? date('d-m-Y H:i:s', $query['expires']) : 'Never';
    $msg = '<strong>' . $this->t('The API Key is:') . '</strong><br><code>' . $this->apiKeyManager->decryptValue($query['data']) . '</code><br><small><strong>' . $this->t('Expires on:') . '</strong> <time>' . $expires . '</time></small>';

    return [
      '#type' => 'markup',
      '#markup' => $msg,
    ];
  }
}
