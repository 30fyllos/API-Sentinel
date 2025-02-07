<?php

declare(strict_types=1);

namespace Drupal\api_sentinel\Form;

use Drupal\api_sentinel\Service\ApiKeyManager;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Random\RandomException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirm form to regenerate an API key for a user.
 */
final class ApiKeyRegenerateConfirmForm extends ConfirmFormBase {

  /**
   * The API Key Manager service.
   *
   * @var ApiKeyManager
   */
  protected ApiKeyManager $apiKeyManager;

  /**
   * ID of the user.
   *
   * @var int|string
   */
  protected int|string $uid;

  /**
   * Constructs the form.
   */
  public function __construct(ApiKeyManager $apiKeyManager) {
    $this->apiKeyManager = $apiKeyManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('api_sentinel.api_key_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'api_sentinel_api_key_regenerate_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $uid = NULL): array
  {
    $this->uid = $uid;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    $user = User::load($this->uid);
    return $this->t('Are you sure you want to regenerate the API key for %user?', ['%user' => $user->getDisplayName()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('api_sentinel.dashboard');
  }

  /**
   * {@inheritdoc}
   * @throws RandomException
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    $user = User::load($this->uid);

    if ($user) {
      $this->apiKeyManager->regenerateApiKey($user);

      $this->messenger()->addStatus($this->t('New API key for %user.', [
        '%user' => $user->getDisplayName()
      ]));
    } else {
      $this->messenger()->addError($this->t('Invalid user selection.'));
    }

    $form_state->setRedirectUrl(new Url('api_sentinel.dashboard'));
  }

}
