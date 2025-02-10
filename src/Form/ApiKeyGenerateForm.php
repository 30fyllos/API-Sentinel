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
   * ID of the user.
   *
   * @var int|null
   */
  protected int|null $uid;

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
  public function buildForm(array $form, FormStateInterface $form_state, string $uid = NULL): array
  {
    // The user ID.
    $this->uid = $uid;

    $form['user'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Select User'),
      '#target_type' => 'user',
      '#description' => $this->t('Select a user to generate an API key for.'),
      '#required' => TRUE,
      '#access' => !$this->uid
    ];

    $form['expires'] = [
      '#type' => 'date',
      '#title' => $this->t('Expiration Date (Optional)'),
      '#description' => $this->t('Leave blank for no expiration.'),
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
    if (!$this->uid) {
      $this->uid = $form_state->getValue('user');
    }
    $user = User::load($this->uid);

    if ($user) {
      $expires = $form_state->getValue('expires') ? strtotime($form_state->getValue('expires')) : NULL;
      $this->apiKeyManager->generateApiKey($user, $expires);

      // Store the API key securely in logs for admin reference.
      $this->messenger()->addStatus($this->t('API key generated for %user', [
        '%user' => $user->getDisplayName(),
      ]));
    }
    else {
      $this->messenger()->addError($this->t('Invalid user selection.'));
    }

    if ($user->hasPermission('administer api keys')) {
      $form_state->setRedirect('api_sentinel.dashboard');
    } else {
      $form_state->setRedirect('api_sentinel.overview');
    }
  }
}
