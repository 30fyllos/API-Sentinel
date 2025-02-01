<?php

namespace Drupal\api_sentinel\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Random\RandomException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\api_sentinel\Service\ApiKeyManager;
use Drupal\Core\Database\Connection;

/**
 * Provides a form to generate an API key for a user.
 */
class ApiKeyGenerateForm extends FormBase {

  /**
   * The API Key Manager service.
   *
   * @var ApiKeyManager
   */
  protected ApiKeyManager $apiKeyManager;

  /**
   * The database connection.
   *
   * @var Connection
   */
  protected Connection $database;

  /**
   * Constructs the form.
   */
  public function __construct(ApiKeyManager $apiKeyManager, Connection $database) {
    $this->apiKeyManager = $apiKeyManager;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('api_sentinel.api_key_manager'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'api_sentinel_generate_api_key';
  }

  /**
   * Builds the API key generation form.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $form['user'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Select User'),
      '#target_type' => 'user',
      '#description' => $this->t('Select a user to generate an API key for.'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate API Key'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Handles form submission.
   * @throws RandomException
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    $uid = $form_state->getValue('user');
    $user = User::load($uid);

    if ($user) {
      $apiKey = $this->apiKeyManager->generateApiKey($user);

      // Store the API key securely in logs for admin reference.
      $this->messenger()->addStatus($this->t('API key generated for %user: %key', [
        '%user' => $user->getDisplayName(),
        '%key' => $apiKey,
      ]));
    }
    else {
      $this->messenger()->addError($this->t('Invalid user selection.'));
    }
  }
}
