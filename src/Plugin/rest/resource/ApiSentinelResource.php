<?php

namespace Drupal\api_sentinel\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\api_sentinel\Authentication\ApiSentinelAuthProvider;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Provides an API endpoint secured with API key authentication.
 *
 * @RestResource(
 *   id = "api_sentinel_resource",
 *   label = @Translation("API Sentinel Secured Resource"),
 *   uri_paths = {
 *     "canonical" = "/api-sentinel/protected-endpoint"
 *   }
 * )
 */
class ApiSentinelResource extends ResourceBase {

  /**
   * The authentication provider.
   *
   * @var ApiSentinelAuthProvider
   */
  protected ApiSentinelAuthProvider $authProvider;

  /**
   * The current user.
   *
   * @var AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs the resource.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param ApiSentinelAuthProvider $authProvider
   *   The API authentication provider.
   * @param AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger, ApiSentinelAuthProvider $authProvider, AccountProxyInterface $currentUser) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->authProvider = $authProvider;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('api_sentinel.auth'),
      $container->get('current_user')
    );
  }

  /**
   * Handles GET requests for the protected API endpoint.
   */
  public function get(Request $request): JsonResponse
  {
    // Authenticate request using API key.
    $user = $this->authProvider->authenticate($request);

    if (!$user) {
      return new JsonResponse(['message' => 'Unauthorized'], 403);
    }

    return new JsonResponse([
      'message' => 'Access granted!',
      'user' => $user->getDisplayName(),
    ]);
  }
}
