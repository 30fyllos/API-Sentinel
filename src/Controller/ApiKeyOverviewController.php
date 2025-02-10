<?php

namespace Drupal\api_sentinel\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\api_sentinel\Service\ApiKeyManagerInterface;
use Drupal\api_sentinel\Service\ApiSentinelNotificationServiceInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for the secure API Key Overview page.
 *
 * This page displays the current user’s API key status (if any) and shows action
 * links for generating, revoking, regenerating, blocking/unblocking the API key, and
 * viewing usage. Access to each action is controlled by user permissions.
 */
class ApiKeyOverviewController extends ControllerBase {

  /**
   * The API Key Manager service.
   *
   * @var ApiKeyManagerInterface
   */
  protected ApiKeyManagerInterface $apiKeyManager;

  /**
   * The Notification service.
   *
   * @var ApiSentinelNotificationServiceInterface
   */
  protected ApiSentinelNotificationServiceInterface $notificationService;

  /**
   * Constructs a new ApiKeyOverviewController.
   *
   * @param ApiKeyManagerInterface $apiKeyManager
   *   The API Key Manager service.
   * @param ApiSentinelNotificationServiceInterface $notificationService
   *   The Notification service.
   */
  public function __construct(ApiKeyManagerInterface $apiKeyManager, ApiSentinelNotificationServiceInterface $notificationService) {
    $this->apiKeyManager = $apiKeyManager;
    $this->notificationService = $notificationService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('api_sentinel.api_key_manager'),
      $container->get('api_sentinel.notification')
    );
  }

  /**
   * Builds the secure API key overview page.
   *
   * @return RedirectResponse|array
   *   A renderable array for the page.
   */
  public function overview(): RedirectResponse|array
  {
    // Ensure the user is logged in.
    $current_user = $this->currentUser();
    if ($current_user->isAnonymous()) {
      // Redirect anonymous users to the login page.
      return $this->redirect('user.login');
    }

    // Load the full user entity.
    $user = User::load($current_user->id());

    $build = [];

    // Check if the user already has an API key.
    $apiKeyId = $this->apiKeyManager->hasApiKey($user);
    if ($apiKeyId) {
      $build['key_info'] = [
        '#markup' => $this->t('You have an API key.'),
      ];
    }
    else {
      $build['key_info'] = [
        '#markup' => $this->t('You do not have an API key.'),
      ];
    }

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

    // Build action links based on the user's permissions.
    $actions = [];
    if ($user->hasPermission('view api keys') && $apiKeyId) {
      $actions[] = [
        'title' => $this->t('View API Key'),
        'url' => Url::fromRoute('api_sentinel.view_api_key', ['uid' => $user->id()], $dialogAttributes),
      ];
    }
    if ($current_user->hasPermission('generate api keys') && !$apiKeyId) {
      $actions[] = [
        'title' => $this->t('Generate API Key'),
        'url' => Url::fromRoute('api_sentinel.generate', ['uid' => $user->id()], $dialogAttributes),
      ];
    }
    if ($apiKeyId) {
      if ($current_user->hasPermission('revoke api keys')) {
        $actions[] = [
          'title' => $this->t('Revoke API Key'),
          'url' => Url::fromRoute('api_sentinel.api_key_revoke_confirm', ['uid' => $user->id()], $dialogAttributes),
        ];
      }
      if ($current_user->hasPermission('regenerate api keys')) {
        $actions[] = [
          'title' => $this->t('Regenerate API Key'),
          'url' => Url::fromRoute('api_sentinel.api_key_regenerate_confirm', ['uid' => $user->id()], $dialogAttributes),
        ];
      }
      if ($current_user->hasPermission('block api keys')) {
        $status = $this->apiKeyManager->getApiKeyStatus($apiKeyId) ? $this->t('Unblock') : $this->t('Block');
        $actions[] = [
          'title' => $this->t('@status API Key', [
            '@status' => $status
          ]),
          'url' => Url::fromRoute('api_sentinel.toggle_block', ['key_id' => $apiKeyId], $dialogAttributes),
        ];
      }
      if ($current_user->hasPermission('usage api keys')) {
        $actions[] = [
          'title' => $this->t('View API Key Usage'),
          'url' => Url::fromRoute('api_sentinel.usage_dialog', ['key_id' => $apiKeyId], $dialogAttributes),
        ];
      }
    }

    if (!empty($actions)) {
      $build['actions'] = [
        '#type' => 'dropbutton',
        '#dropbutton_type' => 'small',
        '#links' => $actions,
        '#attached' => [
          'library' => [
            'core/drupal.dialog.ajax'
          ],
        ],
      ];
    }

    return $build;
  }

}
