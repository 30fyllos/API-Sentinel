<?php

namespace Drupal\api_sentinel\Form;

use Drupal\api_sentinel\Enum\Timeframe;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the API Sentinel settings form.
 *
 * This form provides configuration settings for the API Sentinel module.
 * Performance improvements include:
 * - Caching repeated calls (e.g. Timeframe::options()) in a local variable.
 * - Loading the configuration object once.
 * - Cleaning up default values with array_filter and type casting.
 *
 * For the encryption key, the form will first attempt to load the key from the
 * environment variable 'API_SENTINEL_ENCRYPTION_KEY'. If it is not set, it falls
 * back to the value stored in configuration.
 */
class ApiSentinelSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    // Specify the configuration object names that this form edits.
    return ['api_sentinel.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'api_sentinel_settings_form';
  }

  /**
   * Builds the API Sentinel settings form.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The complete form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Load the configuration once.
    $config = $this->config('api_sentinel.settings');

    // Cache the Timeframe options so we don't call Timeframe::options() multiple times.
    $timeframe_options = Timeframe::options();

    // Whitelisted IP addresses.
    $form['whitelist_ips'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Whitelisted IP Addresses'),
      '#description' => $this->t('Enter allowed IPs (one per line). If set, only these IPs can use API keys.'),
      '#default_value' => implode("\n", (array) $config->get('whitelist_ips') ?? []),
    ];

    // Blacklisted IP addresses.
    $form['blacklist_ips'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Blacklisted IP Addresses'),
      '#description' => $this->t('Enter blocked IPs (one per line). Requests from these IPs will be rejected.'),
      '#default_value' => implode("\n", (array) $config->get('blacklist_ips') ?? []),
    ];

    // Custom HTTP header for API authentication.
    $form['custom_auth_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom Authentication Header'),
      '#description' => $this->t('Enter a custom HTTP header for authentication. Default: X-API-KEY'),
      '#default_value' => $config->get('custom_auth_header') ?? 'X-API-KEY',
      '#required' => TRUE,
    ];

    // Allowed API paths.
    $form['allowed_paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed API Paths'),
      '#description' => $this->t('Enter allowed API paths (one per line). Use wildcards (*) for dynamic segments, e.g., /api/*'),
      '#default_value' => implode("\n", (array) $config->get('allowed_paths') ?? []),
    ];

    // Maximum failed authentication attempts.
    $form['failure_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Max Failed Attempts Before Block'),
      '#default_value' => $config->get('failure_limit', 100),
      '#description' => $this->t('If an API key fails authentication this many times, it will be blocked. Set to 0 to disable.'),
      '#min' => 0,
    ];

    // Timeframe over which failures are counted.
    $form['failure_limit_time'] = [
      '#type' => 'select',
      '#title' => $this->t('Failure Limit Time Per'),
      '#options' => $timeframe_options,
      '#default_value' => $config->get('failure_limit_time', Timeframe::ONE_HOUR->value),
      '#states' => [
        // Show only if failure_limit is not 0.
        'visible' => [
          ':input[name="failure_limit"]' => ['!value' => '0'],
        ],
      ],
    ];

    // Maximum allowed API requests.
    $form['max_rate_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Max Requests Allowed'),
      '#default_value' => $config->get('max_rate_limit', 100),
      '#description' => $this->t('The maximum number of API requests allowed within the selected period. Set to 0 to disable.'),
      '#min' => 0,
    ];

    // Timeframe for rate limiting.
    $form['max_rate_limit_time'] = [
      '#type' => 'select',
      '#title' => $this->t('Rate Limit Time Period'),
      '#options' => $timeframe_options,
      '#default_value' => $config->get('max_rate_limit_time', Timeframe::ONE_HOUR->value),
      '#states' => [
        // Show only if max_rate_limit is not 0.
        'visible' => [
          ':input[name="max_rate_limit"]' => ['!value' => '0'],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Handles form submission.
   *
   * Saves the updated configuration settings and forces key regeneration if
   * the encryption settings have changed.
   *
   * @param array $form
   *   The complete form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Save configuration settings.
    $this->config('api_sentinel.settings')
      ->set('whitelist_ips', array_filter(explode("\n", trim($form_state->getValue('whitelist_ips')))))
      ->set('blacklist_ips', array_filter(explode("\n", trim($form_state->getValue('blacklist_ips')))))
      ->set('custom_auth_header', trim($form_state->getValue('custom_auth_header')))
      ->set('allowed_paths', array_filter(explode("\n", trim($form_state->getValue('allowed_paths')))))
      ->set('failure_limit', $form_state->getValue('failure_limit'))
      ->set('failure_limit_time', $form_state->getValue('failure_limit_time'))
      ->set('max_rate_limit', $form_state->getValue('max_rate_limit'))
      ->set('max_rate_limit_time', $form_state->getValue('max_rate_limit_time'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
