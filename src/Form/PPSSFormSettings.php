<?php

/**
 * @file
 * Content the settings for administering the PPSS form.
 */

namespace Drupal\ppss\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

class PPSSFormSettings extends ConfigFormBase
{
  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    // Unique ID of the form.
    return 'ppss.admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames()
  {
    return [
      'ppss.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $types = ['PayPal', 'Dos', 'Tres'];

    // The settings needed was configured inside ppss.settings.yml file.
    $config = $this->config('ppss.settings');
    
    // Start PalPal general settings.
    $form['ppss_settings']['settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Settings'),
    ];

    $form['ppss_settings']['settings']['description'] = [
      '#markup' => $this->t('Please refer to @link for your settings.', [
        '@link' => Link::fromTextAndUrl($this->t('PayPal developer'),
          Url::fromUri('https://developer.paypal.com/developer/applications/', [
            'attributes' => [
              'onclick' => "target='_blank'",
          ],
        ]))->toString(),
      ]),
    ];

    $form['ppss_settings']['settings']['payment_type'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Subscriptions was pay with PayPal.'),
      '#options' => $types,
      '#default_value' => $config->get('payment_type'),
      '#description' => $this->t('Select the default payment type to show.'),
      '#required' => true,
    ];

    $form['ppss_settings']['settings']['client_id'] = [
      '#type' => 'textfield',
      '#title' => t('PayPal Client ID'),
      '#size' => 100,
      '#default_value' => $config->get('client_id'),
      '#description' => t("Your PayPal client id. It should be similar to:
        AYSq3RDGsmBLJE-otTkBtM-jBRd1TCQwFf9RGfwddNXWz0uFU9ztymylOhRS"),
      '#required' => true,
    ];

    $form['ppss_settings']['settings']['client_secret'] = [
      '#type' => 'textfield',
      '#title' => t('PayPal Client Secret'),
      '#size' => 100,
      '#default_value' => $config->get('client_secret'),
      '#description' => t("Your PayPal client secret. If you don't know, please visiting
        https://developer.paypal.com/developer/applications/ for help."),
      '#required' => true,
    ];

    $form['ppss_settings']['settings']['sandbox_mode'] = [
      '#title' => $this->t('Enable SandBox Mode'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('sandbox_mode'),
      '#description' => $this->t('Allways use the PayPal sandbox virtual testing
        environment before go to production.'),
    ];
    
    // Start fields configuration.
    $form['ppss_settings']['fields'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Fields names settings'),
    ];

    $form['ppss_settings']['fields']['description'] = [
      '#markup' => $this->t('You always need to add one field of this type in your
        custom node type.'),
    ];

    $form['ppss_settings']['fields']['field_price'] = [
      '#title' => $this->t('Price field name'),
      '#type' => 'textfield',
      '#default_value' => $config->get('field_price'),
      '#description' => $this->t("What is the internal Drupal system name of the
        field to store prices. Example: 'field_nvi_price'."),
      '#required' => true,
    ];

    $form['ppss_settings']['fields']['field_description'] = [
      '#title' => $this->t('Description field name'),
      '#type' => 'textfield',
      '#default_value' => $config->get('field_description'),
      '#description' => $this->t("What is the internal Drupal system name of the
        field to store product or service description. Example: 'field_nvi_describe'."),
      '#required' => true,
    ];

    $form['ppss_settings']['fields']['field_sku'] = [
      '#title' => $this->t('SKU field name'),
      '#type' => 'textfield',
      '#default_value' => $config->get('field_sku'),
      '#description' => $this->t("What is the internal Drupal system name of the
        field to store product or service SKU. Example: 'field_nvi_sku'."),
    ];

    // Start payment details.
    $form['ppss_settings']['details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Payment configuration details'),
    ];

    $form['ppss_settings']['details']['currency_code'] = [
      '#title' => $this->t('Currency'),
      '#type' => 'textfield',
      '#default_value' => $config->get('currency_code'),
      '#description' => $this->t('ISO 4217 @link.', [
        '@link' => Link::fromTextAndUrl($this->t('Currency Codes'),
          Url::fromUri('https://www.xe.com/iso4217.php', [
            'attributes' => [
              'onclick' => "target='_blank'",
          ],
        ]))->toString(),
      ]),
      '#required' => true,
    ];

    $form['ppss_settings']['details']['tax'] = [
      '#title' => $this->t('Tax'),
      '#type' => 'textfield',
      '#default_value' => $config->get('tax'),
      '#description' => $this->t('Default tax to charge in all transactions.'),
      '#required' => true,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $config_keys = [
      'client_id', 'client_secret', 'sandbox_mode', 'field_price',
      'field_description', 'field_sku', 'currency_code', 'tax',
    ];
    $ppss_config = $this->config('ppss.settings');
    foreach ($config_keys as $config_key) {
      if ($form_state->hasValue($config_key)) {

        if ($config_key == 'payment_type') {
          $ppss_config->set($config_key, array_filter($form_state->getValue(
            $config_key
          )));
        } else {
          $ppss_config->set($config_key, $form_state->getValue($config_key));
        }
      }
    $ppss_config->save();
    }
    $this->messenger()->addMessage($this->t('The configuration options have been saved.'));
    parent::submitForm($form, $form_state);
  }
}
