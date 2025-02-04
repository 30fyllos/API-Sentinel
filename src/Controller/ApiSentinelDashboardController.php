<?php

namespace Drupal\api_sentinel\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Render\Markup;
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
   * The configuration factory.
   *
   * @var ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs the controller.
   *
   * @param Connection $database
   *   The database connection.
   * @param UrlGeneratorInterface $url_generator
   *    The URL generator service.
   * @param ConfigFactoryInterface $configFactory
   *    The configuration factory.
   */
  public function __construct(Connection $database, UrlGeneratorInterface $url_generator, ConfigFactoryInterface $configFactory) {
    $this->database = $database;
    $this->urlGenerator = $url_generator;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('url_generator'),
      $container->get('config.factory')
    );
  }

  /**
   * Builds the API Sentinel admin dashboard.
   */
  public function dashboard(): array
  {
//    $config = $this->configFactory->get('api_sentinel.settings');
//    $useEncryption = $config->get('use_encryption');
    $apiServiceManager = \Drupal::service('api_sentinel.api_key_manager');
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
      $this->t('API Key'),
      $this->t('Expires'),
      $this->t('Requests in Last Hour'),
      $this->t('Last Access'),
      $this->t('Status'),
      $this->t('Actions'),
    ];

    $query = $this->database->select('api_sentinel_keys', 'ask')
      ->fields('ask', ['id', 'uid', 'api_key_sample', 'created', 'expires', 'blocked'])
      ->execute();

    $rows = [];
    foreach ($query as $record) {
      $uid = $record->uid;
//      if ($useEncryption) {
//        $apiKeyDisplay = \Drupal::service('api_sentinel.api_key_manager')->decryptValue($record->api_key);
//      } else {
//        $apiKeyDisplay = '****' . substr($record->api_key, -6);  // Show only last 6 characters
//      }
      $apiKeyDisplay = '****' . $record->api_key_sample;
      $expires = $record->expires ? date('d-m-Y H:i:s', $record->expires) : 'Never';
      $created = date('d-m-Y H:i:s', $record->created);
      $status = $record->blocked ? $this->t('❌ Blocked') : $this->t('✅ Active');

      $cacheKey = "api_sentinel_rate_limit:{$record->uid}";
      $cache = \Drupal::cache()->get($cacheKey);
      $requestCount = $cache ? $cache->data : $apiServiceManager->apiKeyUsageLast($record->id);

      $dialogAttributes = [
        'attributes' => [
          'class' => [
            'use-ajax'
          ],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode([
            'width' => 600
          ])
        ]
      ];

      // Generate action links
      $actions = [
        '#type' => 'dropbutton',
        '#dropbutton_type' => 'small',
        '#links' => [
          'revoke' => [
            'title' => $this->t('Revoke'),
            'url' => Url::fromRoute('api_sentinel.api_key_revoke_confirm', ['uid' => $uid]),
          ],
          'regenerate' => [
            'title' => $this->t('Regenerate'),
            'url' => Url::fromRoute('api_sentinel.api_key_regenerate_confirm', ['uid' => $uid]),
          ],
          'view_key' => [
            'title' => $this->t('Show API Key'),
            'url' => Url::fromRoute('api_sentinel.show_api_key', ['key_id' => $record->id], $dialogAttributes)
          ],
          'usage' => [
            'title' => $this->t('View Usage'),
            'url' => Url::fromRoute('api_sentinel.usage_dialog', ['key_id' => $record->id], $dialogAttributes)
          ],
          'block' => [
            'title' => $record->blocked ? $this->t('Unblock') : $this->t('Block'),
            'url' => Url::fromRoute('api_sentinel.toggle_block', ['key_id' => $record->id]),
          ]
        ]
      ];


      $rows[] = [
        'uid' => $uid,
        'api_key_sample' => $apiKeyDisplay,
        'expires' => $expires,
        'requests' => $requestCount,
        'created' => $created,
        'status' => $status,
        'actions' => \Drupal::service('renderer')->render($actions),
      ];
    }

    $build['dashboard']['keys_table'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No API keys found.'),
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax'
        ],
      ],
    ];

    return $build;
  }
}
