<?php

/**
 * @file
 * Content the settings for administering the PPSS Webhooks form.
 */

namespace Drupal\ppss\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to configure webhook settings.
 */
class PPSSFormWebhook extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'ppss.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ppss_webhook_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    // Define a field used to capture the stored webhook_id value.
    $form['webhook_id'] = [
      '#type' => 'textfield',
      '#title' => t('Webhook ID'),
      '#required' => TRUE,
      '#default_value' => $config->get('webhook_id'),
      '#description' => t('The ID of the webhook resource for the destination URL to which PayPal delivers the event notification.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->configFactory->getEditable(static::SETTINGS)
      // Save the submitted webhook_id value.
      ->set('webhook_id', $form_state->getValue('webhook_id'))
      ->save();

    $this->messenger()->addMessage($this->t('The configuration PPSS webook options have been saved.'));
    parent::submitForm($form, $form_state);
  }

}
