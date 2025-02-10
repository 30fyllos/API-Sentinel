<?php

namespace Drupal\api_sentinel\Form;

use Drupal\api_sentinel\Service\ApiKeyManager;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for blocking/unblocking an API key.
 */
class ApiKeyBlockConfirmForm extends ConfirmFormBase {

  /**
   * The API key manager service.
   *
   * @var ApiKeyManager
   */
  protected ApiKeyManager $apiKeyManager;

  /**
   * The messenger service.
   *
   * @var MessengerInterface
   */
  protected $messenger;

  /**
   * The current user.
   *
   * @var AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The API key ID.
   *
   * @var int
   */
  protected int $keyId;

  /**
   * Constructs a new confirmation form.
   */
  public function __construct(ApiKeyManager $apiKeyManager, MessengerInterface $messenger, AccountProxyInterface $currentUser) {
    $this->apiKeyManager = $apiKeyManager;
    $this->messenger = $messenger;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('api_sentinel.api_key_manager'),
      $container->get('messenger'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'api_sentinel_api_key_block_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $query = $this->apiKeyManager->getApiKeyStatus($this->keyId);

    if ($query === NULL) {
      return $this->t('API key not found.');
    }

    $status = $query ? $this->t('unblock') : $this->t('block');
    return $this->t('Are you sure you want to @status this API key?', ['@status' => $status]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription()
  {

  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url
  {
    return $this->currentUser->hasPermission('administer api keys') ?
      new Url('api_sentinel.dashboard') :
      new Url('api_sentinel.overview');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $key_id = NULL) {
    $this->keyId = $key_id;

    // Use the service to check if the API key exists.
    if ($this->apiKeyManager->getApiKeyStatus($key_id) === NULL) {
      $this->messenger->addError($this->t('Invalid API key.'));
      return $this->redirect('api_sentinel.dashboard');
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$this->apiKeyManager->toggleApiKeyStatus($this->keyId)) {
      $this->messenger->addError($this->t('API key not found.'));
    }
    else {
      $this->messenger->addStatus($this->t('API key status updated successfully.'));
    }

    if ($this->currentUser->hasPermission('administer api keys')) {
      $form_state->setRedirect('api_sentinel.dashboard');
    } else {
      $form_state->setRedirect('api_sentinel.overview');
    }
  }

}
