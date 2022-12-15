<?php

/**
 * @file
 * A form to sale subscriptions using node details.
 */

namespace Drupal\ppss\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use PayPal\Api\Payer;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use PayPal\Exception\PayPalConnectionException;
use PayPal\Api\Agreement;
use PayPal\Api\Plan;
use PayPal\Api\ChargeModel;
use PayPal\Api\Currency;
use PayPal\Api\MerchantPreferences;
use PayPal\Api\PaymentDefinition;
use PayPal\Api\Patch;
use PayPal\Api\PatchRequest;
use PayPal\Common\PayPalModel;

/**
* Provides an PPSS only one button form.
*/
class PPSSBtnPaySubscription extends FormBase
{
  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'ppssbutton_paysubscription';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    // Atempt to get the fully loaded node object of the viewed page and settings.
    $node = \Drupal::routeMatch()->getParameter('node');
    $config = \Drupal::config('ppss.settings');
    $clientId = $config->get('client_id');
    $clientSecret = $config->get('client_secret');

    // Only shows form if credentials are correctly configured and content is a node.
    if (!(empty($clientId) || empty($clientSecret) || (is_null($node)))) {
      // Creates the button for pay.
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => t('Buy Subscription Now'),
      ];
    } else {
      // Nothing to display.
      $message = "PPSS module don't has configured properly,
        please review your settings.";
      \Drupal::logger('system')->alert($message);
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    // Atempt to get the fully loaded node object of the viewed page and settings.
    $node = \Drupal::routeMatch()->getParameter('node');
    $config = \Drupal::config('ppss.settings');
    $clientId = $config->get('client_id');
    $clientSecret = $config->get('client_secret');
    $fieldPrice = $config->get('field_price');
    $fieldDescription = $config->get('field_description');
    $fieldSku = $config->get('field_sku');
    $fieldRole = $config->get('field_role');
    $currency = $config->get('currency_code');
    $taxAmount = floatval($config->get('tax'))/100;

    if (!(empty($clientId)) || !(empty($clientSecret)) || !(is_null($node))) {

      $price = floatval($node->get($fieldPrice)->getString());
      $description = $node->get($fieldDescription)->getString();
      $sku = strlen($fieldSku) == 0 ? '' : $node->get($fieldSku)->getString();
      $newRole = strlen($fieldRole) == 0 ? '' : $node->get($fieldRole)->getString();
      $tax = $price * $taxAmount;
      $total = $price * (1+$taxAmount);

      // Create a new billing plan
      $plan = new Plan();
      $plan->setName('Basico-C')
        ->setDescription($description)
        ->setType('INFINITE');  // Valid parameters are INFINITE or FIXED

      // Set billing plan definitions
      $paymentDefinition = new PaymentDefinition();
      $paymentDefinition
        ->setName('Regular Payments')
        ->setType('REGULAR')    // Valid values are TRIAL or REGULAR
        ->setFrequency('MONTH') // Valid values are DAY,WEEK,MONTH or YEAR
        ->setFrequencyInterval('1')
        //  If payment definition type is REGULAR, cycles can only be null or 0
        // for an UNLIMITED plan
        ->setCycles('0')
        ->setAmount(new Currency(array(
          'value' => $price,
          'currency' => $currency
          )));

      // Set charge models
      $chargeModel = new ChargeModel();
      $chargeModel->setType('TAX')->setAmount(new Currency(array(
        'value' => $tax,
        'currency' => $currency
        )));

      $paymentDefinition->setChargeModels(array($chargeModel));
      
      // Set the urls that the buyer must be redirected to after
      // payment approval/ cancellation.
      $baseUrl = \Drupal::request()->getSchemeAndHttpHost();
        
      // Set merchant preferences
      $merchantPreferences = new MerchantPreferences();
      $merchantPreferences->setReturnUrl("$baseUrl/venta/exitosa?roleid=$newRole")
        ->setCancelUrl($baseUrl)
        ->setAutoBillAmount('yes')
        ->setInitialFailAmountAction('CONTINUE')
        ->setMaxFailAttempts('0')
        ->setSetupFee(new Currency(array(
          'value' => $total,
          'currency' => $currency
        )));
        
      // A Subscription Plan Resource; create one using the above info.
      $plan->setPaymentDefinitions(array($paymentDefinition));
      $plan->setMerchantPreferences($merchantPreferences);

      $apiContext = new ApiContext(
        new OAuthTokenCredential($clientId, $clientSecret)
        );

      $apiContext->setConfig(
        array(
          'mode' => 'sandbox',
          'log.LogEnabled' => true,
          'log.FileName' => '../PayPal.log',
          'log.LogLevel' => 'DEBUG', // PLEASE USE `INFO` LEVEL FOR LOGGING IN LIVE ENVIRONMENTS
          'cache.enabled' => true,
          //'cache.FileName' => '/PaypalCache' // for determining paypal cache directory
          'http.CURLOPT_CONNECTTIMEOUT' => 30
          // 'http.headers.PayPal-Partner-Attribution-Id' => '123123123'
          //'log.AdapterFactory' => '\PayPal\Log\DefaultLogFactory'
          // Factory class implementing \PayPal\Log\PayPalLogFactory
        ));

      // Create a plan by calling the 'create' method passing it a valid apiContext.
      try {
        $createdPlan = $plan->create($apiContext);
      } catch (\PayPal\Exception\PayPalConnectionException $ex) {
        // Save error message details in Drupal log.
        \Drupal::logger('paypal')->error($ex->getData());
        die($ex);
      }

      // Create new agreement
      $startDate = date('c', time() + 3600);
      $agreement = new Agreement();
      $agreement->setName($description . t('Agreement'))
        ->setDescription($description . t('Billing Agreement'))
        ->setStartDate($startDate);
    
      try {
        $patch = new Patch();
        $value = new PayPalModel('{"state":"ACTIVE"}');
        $patch->setOp('replace')
            ->setPath('/')
            ->setValue($value);
        $patchRequest = new PatchRequest();
        $patchRequest->addPatch($patch);
        $createdPlan->update($patchRequest, $apiContext);
        $patchedPlan = Plan::get($createdPlan->getId(), $apiContext);
      } catch (\PayPal\Exception\PayPalConnectionException $ex) {
        // Save error message details in Drupal log.
        \Drupal::logger('paypal')->error($ex->getData());
        die($ex);
      }

      // Set plan id
      $plan = new Plan();
      $plan->setId($patchedPlan->getId());
      $agreement->setPlan($plan);

      // Set payment method. By now always PayPal
      $payer = new Payer();
      $payer->setPaymentMethod('paypal');
      $agreement->setPayer($payer);

      // Create agreement
      try {
        $agreement = $agreement->create($apiContext);
  
        // Extract approval URL to redirect user
        $approvalUrl = $agreement->getApprovalLink();
  
        header("Location: " . $approvalUrl);
        exit();
      } catch (\PayPal\Exception\PayPalConnectionException $ex) {
        $message = "Unable to charge with PayPal at this time due to validation error.
        Please try again.";

        // Show error message to the user and save details in Drupal log.
        \Drupal::messenger()->addError(t($message));
        \Drupal::logger('paypal')->error($ex->getData());
      }
    }
  }
}
