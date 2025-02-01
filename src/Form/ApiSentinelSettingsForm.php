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

    return parent::buildForm($form, $form_state);
  }

  /**
   * Handles form submission.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('api_sentinel.settings')
      ->set('whitelist_ips', array_filter(explode("\n", trim($form_state->getValue('whitelist_ips')))))
      ->set('blacklist_ips', array_filter(explode("\n", trim($form_state->getValue('blacklist_ips')))))
      ->set('custom_auth_header', trim($form_state->getValue('custom_auth_header')))
      ->set('allowed_paths', array_filter(explode("\n", trim($form_state->getValue('allowed_paths')))))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
