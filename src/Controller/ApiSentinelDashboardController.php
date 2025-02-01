<?php

namespace Drupal\api_sentinel\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Provides the API Sentinel admin dashboard using fieldsets.
 */
class ApiSentinelDashboardController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var Connection
   */
  protected $database;

  /**
   * The URL generator service.
   *
   * @var UrlGeneratorInterface
   */
  protected UrlGeneratorInterface $urlGenerator;

  /**
   * Constructs the controller.
   *
   * @param Connection $database
   *   The database connection.
   * @param UrlGeneratorInterface $url_generator
   *    The URL generator service.
   */
  public function __construct(Connection $database, UrlGeneratorInterface $url_generator) {
    $this->database = $database;
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('url_generator')
    );
  }

  /**
   * Builds the API Sentinel admin dashboard.
   */
  public function dashboard() {
    $build = [];

    // Dashboard
    $build['dashboard'] = [
      '#type' => 'fieldset',
      'description' => [
        '#markup' => $this->t('View and manage API keys.')
      ],
      '#title' => $this->t('API Key Dashboard'),
    ];

    // Bulk generate API Key
    $build['generate']['bulk'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Generate API Key by groups'),
      'description' => [
        '#markup' => $this->t('Generate API key to a group of user.')
      ],
      'form' => \Drupal::formBuilder()->getForm('Drupal\api_sentinel\Form\ApiKeyGenerateAllForm'),
    ];

    // Generate API Key by user
    $build['generate']['single'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Generate API Key'),
      'description' => [
        '#markup' => $this->t('Generate a new API key for a user.')
      ],
      'form' => \Drupal::formBuilder()->getForm('Drupal\api_sentinel\Form\ApiKeyGenerateForm'),
    ];

    // Generate API Key by user
    $build['settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('White list & Black list IP addresses'),
      'description' => [
        '#markup' => $this->t('A list of IP addresses to allow or block the access.')
      ],
      'form' => \Drupal::formBuilder()->getForm('Drupal\api_sentinel\Form\ApiSentinelSettingsForm'),
    ];

    // Build API keys table
    $header = [
      $this->t('User ID'),
      $this->t('API Key (Last 6 chars)'),
      $this->t('Requests in Last Hour'),
      $this->t('Last Access'),
      $this->t('Actions'),
    ];

    $query = $this->database->select('api_sentinel_keys', 'ask')
      ->fields('ask', ['uid', 'api_key', 'created'])
      ->execute();

    $rows = [];
    foreach ($query as $record) {
      $uid = $record->uid;
      $apiKey = $record->api_key;
      $lastAccess = date('Y-m-d H:i:s', $record->created);

      $cacheKey = "api_sentinel_rate_limit:{$record->uid}";
      $cache = \Drupal::cache()->get($cacheKey);
      $requestCount = $cache ? $cache->data : 0;

      $rows[] = [
        'uid' => $uid,
        'api_key' => $apiKey,
        'requests' => $requestCount,
        'last_access' => $lastAccess,
        'actions' => $this->t('<a href="@revokeUrl">Revoke</a> | <a href="@regenerateUrl">Regenerate</a>', [
          '@revokeUrl' => $this->getRevokeUrl($record->uid),
          '@regenerateUrl' => $this->getRegenerateUrl($record->uid),
        ]),
      ];
    }

    $build['dashboard']['keys_table'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No API keys found.'),
    ];

    return $build;
  }

  /**
   * Generates the revoke URL for a user.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return string
   *   The URL to revoke the API key.
   */
  protected function getRevokeUrl(int $uid): string
  {
    return $this->urlGenerator->generateFromRoute('api_sentinel.api_key_revoke_confirm', ['uid' => $uid]);
  }

  /**
   * Generates the regenerate URL for a user.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return string
   *   The URL to regenerate the API key.
   */
  protected function getRegenerateUrl(int $uid): string
  {
    return $this->urlGenerator->generateFromRoute('api_sentinel.api_key_regenerate_confirm', ['uid' => $uid]);
  }
}
