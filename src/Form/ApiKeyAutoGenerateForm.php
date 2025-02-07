<?php

namespace Drupal\api_sentinel\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a configuration form for auto-generating API keys on new registration.
 *
 * When enabled, any new user registering with one of the selected roles will
 * automatically receive an API key. This form saves settings via AJAX on value
 * change.
 */
class ApiKeyAutoGenerateForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    // This form stores settings in the "api_sentinel.settings" config.
    return ['api_sentinel.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'api_sentinel_auto_generate_form';
  }

  /**
   * Constructs the form.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return parent::create($container);
  }

  /**
   * Builds the auto-generation configuration form.
   *
   * This form includes:
   * - A checkbox to enable auto-generation.
   * - (When enabled) Checkboxes for selecting roles.
   * - (When enabled) A date field for default expiration for auto-generated keys.
   *
   * The form saves its values via AJAX on change.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The complete form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Load configuration once.
    $config = $this->config('api_sentinel.settings');

    // Create a container for the auto-generation settings.
    $form['auto_generate_settings'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'auto-generate-settings-wrapper'],
    ];

    // Checkbox to enable auto-generation.
    $form['auto_generate_settings']['auto_generate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Auto-Generate API Keys on New Registration'),
      '#default_value' => $config->get('auto_generate_enabled') ?: 0,
      '#ajax' => [
        'callback' => '::ajaxSaveCallback',
        'wrapper'  => 'auto-generate-settings-wrapper',
        'event'    => 'change',
      ],
      '#description' => $this->t('When enabled, new users with the selected roles will automatically receive an API key.'),
    ];

    // Determine whether the auto_generate checkbox is checked.
    // We check the current form state value (if set) or fall back to the stored config.
    $auto_generate = $form_state->getValue('auto_generate', $config->get('auto_generate_enabled'));

    // If auto-generation is enabled, display additional settings.
    if ($auto_generate) {
      // Load all roles (except anonymous).
      $roles = Role::loadMultiple();
      $role_options = [];
      foreach ($roles as $role) {
        if ($role->id() == 'anonymous') {
          continue;
        }
        $role_options[$role->id()] = $role->label();
      }

      // Checkboxes for selecting roles.
      $form['auto_generate_settings']['auto_generate_roles'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Select Roles for Auto-Generation'),
        '#options' => $role_options,
        '#default_value' => $config->get('auto_generate_roles') ?: [],
        '#ajax' => [
          'callback' => '::ajaxSaveCallback',
          'wrapper'  => 'auto-generate-settings-wrapper',
          'event'    => 'click',
        ],
        '#description' => $this->t('Users registering with these roles will automatically receive an API key.'),
      ];

      $form['auto_generate_settings']['duration_wrapper'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Default Expiration Date'),
        '#description' => $this->t('Optional expiration date for auto-generated API keys. Leave blank for no expiration.'),
        '#attributes' => ['class' => ['duration-container']],
      ];

      $form['auto_generate_settings']['duration_wrapper']['auto_generate_duration'] = [
        '#type' => 'number',
        '#title' => $this->t('Duration'),
        '#min' => 0,
        '#max' => 100,
        '#default_value' => $config->get('auto_generate_duration') ?: 0,
        '#ajax' => [
          'callback' => '::ajaxSaveCallback',
          'wrapper'  => 'auto-generate-settings-wrapper',
          'event'    => 'change',
        ],
      ];

      $form['auto_generate_settings']['duration_wrapper']['auto_generate_duration_unit'] = [
        '#type' => 'select',
        '#title' => $this->t('Unit'),
        '#options' => [
          'days' => $this->t('Day(s)'),
          'months' => $this->t('Month(s)'),
          'years' => $this->t('Year(s)'),
        ],
        '#default_value' => $config->get('auto_generate_duration_unit') ?: 'years',
        '#ajax' => [
          'callback' => '::ajaxSaveCallback',
          'wrapper'  => 'auto-generate-settings-wrapper',
          'event'    => 'change',
        ],
        '#states' => [
          // Show only if failure_limit is not 0.
          'visible' => [
            ':input[name="auto_generate_duration"]' => ['!value' => '0'],
          ],
        ],
      ];

    }

    $buildForm = parent::buildForm($form, $form_state);
    unset($buildForm['actions']);
    return $buildForm;
  }

  /**
   * AJAX callback to save configuration on value change.
   *
   * This callback is triggered when any of the auto-generation fields change.
   * It saves the configuration and returns the updated container.
   *
   * @param array $form
   *   The full form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The updated auto-generation settings container.
   */
  public function ajaxSaveCallback(array &$form, FormStateInterface $form_state) {
    // Save the configuration using our submitForm() method.
    $this->submitForm($form, $form_state);
    // Return the container that holds our auto-generation settings.
    return $form['auto_generate_settings'];
  }

  /**
   * {@inheritdoc}
   *
   * Saves the auto-generation configuration settings.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('api_sentinel.settings');

    // Save the auto-generation enabled flag.
    $config->set('auto_generate_enabled', $form_state->getValue('auto_generate'));
    // Save roles and expiration only if auto-generation is enabled.
    if ($form_state->getValue('auto_generate')) {
      $config->set('auto_generate_roles', $form_state->getValue('auto_generate_roles'));
      $config->set('auto_generate_duration', $form_state->getValue('auto_generate_duration'));
      $config->set('auto_generate_duration_unit', $form_state->getValue('auto_generate_duration_unit'));
    }
    else {
      // Clear the settings if auto-generation is disabled.
      $config->set('auto_generate_roles', []);
      $config->set('auto_generate_duration', 0);
      $config->set('auto_generate_duration_unit', 'years');
    }
    $config->save();
  }

}
