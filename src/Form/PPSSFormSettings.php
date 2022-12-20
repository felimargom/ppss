<?php

/**
 * @file
 * Content the settings for administering the PPSS form.
 */

namespace Drupal\ppss\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;


class PPSSFormSettings extends ConfigFormBase
{
  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * Constructs an AutoParagraphForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The path validator.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, PathValidatorInterface $path_validator)
  {
    $this->entityTypeManager = $entityTypeManager;
    $this->pathValidator = $path_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('path.validator'),
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    // Unique ID of the form.
    return 'ppss_admin_settings';
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
    // Get the internal node type machine name.
    $existingContentTypeOptions = $this->getExistingContentTypes();
    $paymentGateways = ['PayPal' => 'PayPal'];

    // The settings needed was configured inside ppss.settings.yml file.
    $config = $this->config('ppss.settings');

    $form['init_message'] = [
      '#markup' => $this->t('**Before configure this module always execute "composer require
        paypal/rest-api-sdk-php:*" on command line'),
    ];

    // General settings.
    $form['allowed_gateways'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Subscriptions can be pay with all selected payments gateways.'),
      '#options' => $paymentGateways,
      '#default_value' => $config->get('allowed_gateways'),
      '#description' => $this->t('Select all the payments gateways you like to use to enable for.'),
      '#required' => true,
    ];

    $form['content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('The content types to enable PPSS button for'),
      '#default_value' => $config->get('content_types'),
      '#options' => $existingContentTypeOptions,
      '#empty_option' => $this->t('- Select an existing content type -'),
      '#description' => $this->t('On the specified node types, an PPSS button
        will be available and can be shown to make purchases.'),
      '#required' => true,
    ];
    
    $form['return_url'] = [
      '#type' => 'details',
      '#title' => $this->t('URL of return pages'),
      '#open' => TRUE,
    ];

    $form['return_url']['success_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Success URL'),
      '#default_value' => $config->get('success_url'),
      '#description' => $this->t('What is the return URL when a new successful sale was made? Specify a relative URL.'),
      '#size' => 40,
      '#required' => true,
    ];

    $form['return_url']['error_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Error URL'),
      '#default_value' => $config->get('error_url'),
      '#description' => $this->t('What is the return URL when a sale fails? Specify a relative URL.
        Leave blank to display the default front page.'),
      '#size' => 40,
      '#required' => true,
    ];

    // Start fields configuration.
    $form['fields'] = [
      '#type' => 'details',
      '#title' => $this->t('Fields names settings'),
      '#open' => FALSE,
    ];

    $form['fields']['description'] = [
      '#markup' => $this->t('You always need to add one field of this type in your
        custom node type.'),
    ];

    $form['fields']['field_price'] = [
      '#title' => $this->t('Price field name'),
      '#type' => 'textfield',
      '#default_value' => $config->get('field_price'),
      '#description' => $this->t("What is the internal Drupal system name of the
        field to store prices. Example: 'field_nvi_price'."),
      '#required' => true,
    ];

    $form['fields']['field_frequency'] = [
      '#title' => $this->t('Frecuency field name'),
      '#type' => 'textfield',
      '#default_value' => $config->get('field_frequency'),
      '#description' => $this->t("What is the internal Drupal system name of the
        field to store frequency in a recurring subscription agreement.
        Example: 'field_nvi_frecuency'."),
      '#required' => true,
    ];

    $form['fields']['field_frequency_interval'] = [
      '#title' => $this->t('Frequency interval field name'),
      '#type' => 'textfield',
      '#default_value' => $config->get('field_frequency_interval'),
      '#description' => $this->t("What is the internal Drupal system name of the
        field to save interval between charges. Example: 'field_frequency_interval'."),
      '#required' => true,
    ];

    $form['fields']['field_description'] = [
      '#title' => $this->t('Description field name'),
      '#type' => 'textfield',
      '#default_value' => $config->get('field_description'),
      '#description' => $this->t("What is the internal Drupal system name of the
        field to store product or service description. Example: 'field_nvi_describe'."),
      '#required' => true,
    ];

    $form['fields']['field_role'] = [
      '#title' => $this->t('User role field name'),
      '#type' => 'textfield',
      '#default_value' => $config->get('field_role'),
      '#description' => $this->t("What is the internal Drupal system name of the
        field to store new user role assigned after purchased a plan. Example: 'field_nvi_role'."),
    ];

    $form['fields']['field_sku'] = [
      '#title' => $this->t('SKU field name'),
      '#type' => 'textfield',
      '#default_value' => $config->get('field_sku'),
      '#description' => $this->t("What is the internal Drupal system name of the
        field to store product or service SKU. Example: 'field_nvi_sku'."),
    ];

    $form['ppss_settings']['fields']['field_role'] = [
      '#title' => $this->t('Role field name'),
      '#type' => 'textfield',
      '#default_value' => $config->get('field_role'),
      '#description' => $this->t("What is the internal Drupal system name of the
        field that saves the new role assigned to the user after purchase? Example: 'field_nvi_role'."),
    ];

    // Start payment details.
    $form['details'] = [
      '#type' => 'details',
      '#title' => $this->t('Payment configuration details'),
      '#open' => FALSE,
    ];

    $form['details']['currency_code'] = [
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

    $form['details']['tax'] = [
      '#title' => $this->t('Tax'),
      '#type' => 'textfield',
      '#default_value' => $config->get('tax'),
      '#description' => $this->t('Default tax to charge in all transactions.'),
      '#required' => true,
    ];
    
    // Start PayPal general settings.
    $form['paypal_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('PayPal Settings'),
      '#open' => FALSE,
    ];

    $form['paypal_settings']['description'] = [
      '#markup' => $this->t('Please refer to @link for your settings.', [
        '@link' => Link::fromTextAndUrl($this->t('PayPal developer'),
          Url::fromUri('https://developer.paypal.com/developer/applications/', [
            'attributes' => [
              'onclick' => "target='_blank'",
          ],
        ]))->toString(),
      ]),
    ];

    $form['paypal_settings']['client_id'] = [
      '#type' => 'textfield',
      '#title' => t('PayPal Client ID'),
      '#size' => 100,
      '#default_value' => $config->get('client_id'),
      '#description' => t("Your PayPal client id. It should be similar to:
        AYSq3RDGsmBLJE-otTkBtM-jBRd1TCQwFf9RGfwddNXWz0uFU9ztymylOhRS"),
      '#required' => true,
    ];

    $form['paypal_settings']['client_secret'] = [
      '#type' => 'textfield',
      '#title' => t('PayPal Client Secret'),
      '#size' => 100,
      '#default_value' => $config->get('client_secret'),
      '#description' => t("Your PayPal client secret. If you don't know, please visiting
        https://developer.paypal.com/developer/applications/ for help."),
      '#required' => true,
    ];

    $form['paypal_settings']['sandbox_mode'] = [
      '#title' => $this->t('Enable SandBox Mode'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('sandbox_mode'),
      '#description' => $this->t('Allways use the PayPal sandbox virtual testing
        environment before go to production.'),
    ];

    $form['last_message'] = [
      '#markup' => $this->t('Remember always flush the cache after save this
        configuration form.'),
    ];

    return parent::buildForm($form, $form_state);
  }
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate return URL pages path.
    //dump(($form_state->getValue('success_url')))
    if (!$this->pathValidator->isValid($form_state->getValue('success_url'))) {
      $form_state->setErrorByName('success_url', $this->t("Either the path '%path' is invalid or
        you do not have access to it.", ['%path' => $form_state->getValue('success_url')]));
    }
    if (!$this->pathValidator->isValid($form_state->getValue('error_url'))) {
      $form_state->setErrorByName('error_url', $this->t("Either the path '%path' is invalid or
        you do not have access to it.", ['%path' => $form_state->getValue('error_url')]));
    }
    parent::validateForm($form, $form_state);

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $config_keys = [
      'content_types', 'client_id', 'client_secret', 'sandbox_mode', 'field_price',
      'field_description', 'field_role', 'field_sku', 'currency_code', 'allowed_gateways',
      'field_frequency', 'field_frequency_interval', 'success_url', 'error_url', 'tax',
    ];
    $ppss_config = $this->config('ppss.settings');
    foreach ($config_keys as $config_key) {
      if ($form_state->hasValue($config_key)) {

        if ($config_key == 'allowed_gateways' || $config_key == 'content_types') {
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

  /**
   * Returns a list of all the content types currently installed.
   *
   * @return array
   *   An array of content types.
   */
  public function getExistingContentTypes()
  {
    $types = [];
    $contentTypes = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    foreach ($contentTypes as $contentType) {
      $types[$contentType->id()] = $contentType->label();
    }
    return $types;
  }
}
