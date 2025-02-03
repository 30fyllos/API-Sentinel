<?php

namespace Drupal\api_sentinel\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the API Sentinel settings form.
 */
class ApiSentinelSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['api_sentinel.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'api_sentinel_settings_form';
  }

  /**
   * Builds the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('api_sentinel.settings');

    $form['whitelist_ips'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Whitelisted IP Addresses'),
      '#description' => $this->t('Enter allowed IPs (one per line). If set, only these IPs can use API keys.'),
      '#default_value' => implode("\n", $config->get('whitelist_ips') ?? []),
    ];

    $form['blacklist_ips'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Blacklisted IP Addresses'),
      '#description' => $this->t('Enter blocked IPs (one per line). Requests from these IPs will be rejected.'),
      '#default_value' => implode("\n", $config->get('blacklist_ips') ?? []),
    ];

    $form['custom_auth_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom Authentication Header'),
      '#description' => $this->t('Enter a custom HTTP header for authentication. Default: X-API-KEY'),
      '#default_value' => $config->get('custom_auth_header') ?? 'X-API-KEY',
      '#required' => TRUE,
    ];

    $form['allowed_paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed API Paths'),
      '#description' => $this->t('Enter allowed API paths (one per line). Use wildcards (*) for dynamic segments, e.g., /api/*'),
      '#default_value' => implode("\n", $config->get('allowed_paths') ?? []),
    ];

    $form['use_encryption'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Encrypt API keys'),
      '#default_value' => $config->get('use_encryption', FALSE),
      '#description' => $this->t('Store API keys encrypted. This options will allow you to have access to the keys.<br><strong>Warning:</strong> Enabling this will force regeneration of all existing keys.'),
    ];

    $form['encryption_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Encryption Key'),
      '#default_value' => $config->get('encryption_key', ''),
      '#attributes' => ['readonly' => 'readonly'],
      '#disabled' => TRUE,
      '#access' => $this->currentUser()->id() == 1,
      '#description' => $this->t('This key is automatically generated and used for encrypting API keys.'),
      '#states' => [
        'visible' => [
          ':input[name="use_encryption"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Handles form submission.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('api_sentinel.settings');

    // Detect if encryption setting has changed
    $previousEncryption = $config->get('use_encryption');
    $newEncryption = $form_state->getValue('use_encryption');

    $encryptionKey = trim($form_state->getValue('encryption_key'));
    if ($newEncryption && strlen($encryptionKey) !== 32) {
      $form_state->setErrorByName('encryption_key', $this->t('Encryption key must be exactly 32 characters long.'));
      return;
    }

    $this->config('api_sentinel.settings')
      ->set('whitelist_ips', array_filter(explode("\n", trim($form_state->getValue('whitelist_ips')))))
      ->set('blacklist_ips', array_filter(explode("\n", trim($form_state->getValue('blacklist_ips')))))
      ->set('custom_auth_header', trim($form_state->getValue('custom_auth_header')))
      ->set('allowed_paths', array_filter(explode("\n", trim($form_state->getValue('allowed_paths')))))
      ->set('store_plaintext_keys', $form_state->getValue('store_plaintext_keys'))
      ->set('use_encryption', $newEncryption)
      ->set('encryption_key', $encryptionKey)
      ->save();

    parent::submitForm($form, $form_state);

    // If encryption mode changed, force key regeneration
    if ($previousEncryption !== $newEncryption) {
      \Drupal::messenger()->addWarning($this->t('API key encryption setting changed. All keys have been regenerated.'));
      \Drupal::service('api_sentinel.api_key_manager')->forceRegenerateAllKeys();
    }
  }

}
