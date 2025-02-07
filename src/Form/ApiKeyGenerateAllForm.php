<?php

namespace Drupal\api_sentinel\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Random\RandomException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\api_sentinel\Service\ApiKeyManager;
use Drupal\user\Entity\Role;

/**
 * Provides a form to generate API keys for users based on roles.
 */
class ApiKeyGenerateAllForm extends FormBase {

  /**
   * The API Key Manager service.
   *
   * @var \Drupal\api_sentinel\Service\ApiKeyManager
   */
  protected $apiKeyManager;

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
    return new static($container->get('api_sentinel.api_key_manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'api_sentinel_generate_all_api_keys';
  }

  /**
   * Builds the API key generation form with a role filter.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get all user roles.
    $roles = Role::loadMultiple();
    $role_options = [];
    foreach ($roles as $role) {
      if ($role->id() == 'anonymous') continue;
      $role_options[$role->id()] = $role->label();
    }

    $form['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select User Roles'),
      '#options' => $role_options,
      '#description' => $this->t('Only users with the selected roles will receive API keys.'),

    ];

    // Disable other checkboxes if "Authenticated" is selected.
    foreach ($role_options as $role_id => $label) {
      if ($role_id !== 'authenticated') {
        $form['roles'][$role_id] = [
          '#states' => [
            // Disable and checked all other checkboxes when "Authenticated" is checked.
            'disabled' => [
              ':input[name="roles[authenticated]"]' => ['checked' => TRUE],
            ],
            'checked' => [
              ':input[name="roles[authenticated]"]' => ['checked' => TRUE],
            ],
          ]
        ];
      }
    }

    $form['expires'] = [
      '#type' => 'date',
      '#title' => $this->t('Expiration Date (Optional)'),
      '#description' => $this->t('Leave blank for no expiration.'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate API Keys'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Ajax callback to dynamically disable roles.
   */
  public function updateRoleSelection(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Handles form submission.
   * @throws RandomException
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    $selected_roles = array_filter($form_state->getValue('roles')); // Remove empty values

    if (empty($selected_roles)) {
      $this->messenger()->addWarning($this->t('No roles selected. API keys were not generated.'));
      return;
    }

    $expires = $form_state->getValue('expires') ? strtotime($form_state->getValue('expires')) : NULL;

    $count = $this->apiKeyManager->generateApiKeysForAllUsers($selected_roles, $expires);

    if ($count > 0) {
      $this->messenger()->addStatus($this->t('%count API keys generated for users.<br>', ['%count' => $count]));
    } else {
      $this->messenger()->addWarning($this->t('No API keys were generated. Ensure users have the selected roles.'));
    }
  }
}
