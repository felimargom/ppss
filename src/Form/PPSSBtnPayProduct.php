<?php

/**
 * @file
 * A form to sale single products/services using node details.
 */

namespace Drupal\ppss\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use PayPal\Api\Payer;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use PayPal\Exception\PayPalConnectionException;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payment;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;


/**
* Provides an PPSS only one button form.
*/
class PPSSBtnPayProduct extends FormBase
{
  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'ppssbutton_payproduct';
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
        '#value' => t('Buy Product Now'),
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
    $sandbox = $config->get('sandbox_mode') == TRUE ? 'sandbox' : 'live';
    $logLevel = $config->get('sandbox_mode') == TRUE ? 'DEBUG' : 'INFO';
    $fieldPrice = $config->get('field_price');
    $fieldDescription = $config->get('field_description');
    $fieldSku = $config->get('field_sku');
    $fieldRole = $config->get('field_role');
    $successUrl = $config->get('success_url');
    $errorUrl = $config->get('error_url');
    $currency = $config->get('currency_code');
    $taxAmount = floatval($config->get('tax'))/100;

    if (!(empty($clientId)) || !(empty($clientSecret)) || !(is_null($node))) {

      $price = floatval($node->get($fieldPrice)->getString());
      $description = $node->get($fieldDescription)->getString();
      $sku = strlen($fieldSku) == 0 ? '' : $node->get($fieldSku)->getString();
      $newRole = strlen($fieldRole) == 0 ? '' : $node->get($fieldRole)->getString();
      $tax = $price * $taxAmount;
      $total = $price * (1+$taxAmount);

      // Set payment method. By now always PayPal
      $payer = new Payer();
      $payer->setPaymentMethod('paypal');

      // ### Itemized information
      // (Optional) Lets you specify item wise
      // information
      $item = new Item();
      if (strlen($sku) == 0) {
        $item->setName($description)
          ->setCurrency($currency)
          ->setQuantity(1)
          ->setPrice($price);
      } else {
        $item->setName($description)
          ->setCurrency($currency)
          ->setQuantity(1)
          ->setSku($sku) //  Similar to `item_number` in Classic API
          ->setPrice($price);
      }

      $itemList = new ItemList();
      $itemList->setItems(array($item));
      
      // Additional payment details to set payment information such as tax,
      // shipping charges etc.
      $details = new Details();
      $details->setTax($tax)
        ->setSubtotal($price);
      
      // Specify a payment amount and additional details such as shipping, tax.
      $amount = new Amount();
      $amount->setCurrency($currency)
        ->setTotal($total)
        ->setDetails($details);
      
      // A transaction defines the contract of a payment - what is the payment
      // for and who is fulfilling it.
      $transaction = new Transaction();
      $transaction->setAmount($amount)
        ->setItemList($itemList)
        ->setDescription($description)
        ->setInvoiceNumber(uniqid());
      
      // Set the urls that the buyer must be redirected to after
      // payment approval/ cancellation.
      $baseUrl = \Drupal::request()->getSchemeAndHttpHost();
      $redirectUrls = new RedirectUrls();
      $redirectUrls->setReturnUrl("$baseUrl$successUrl?roleid=".$newRole)
        ->setCancelUrl($baseUrl.$errorUrl);
      
      // A Payment Resource; create one using the above types and intent set to 'sale'
      $payment = new Payment();
      $payment->setIntent("sale")
        ->setPayer($payer)
        ->setRedirectUrls($redirectUrls)
        ->setTransactions(array($transaction));

      $apiContext = new ApiContext(
        new OAuthTokenCredential($clientId, $clientSecret)
      );

      $apiContext->setConfig(
        array(
          'mode' => $sandbox,
          'log.LogEnabled' => true,
          'log.FileName' => '../PayPal.log',
          'log.LogLevel' => $logLevel, // PLEASE USE `INFO` LEVEL FOR LOGGING IN LIVE ENVIRONMENTS
          'cache.enabled' => true,
          //'cache.FileName' => '/PaypalCache' // for determining paypal cache directory
          'http.CURLOPT_CONNECTTIMEOUT' => 30
          // 'http.headers.PayPal-Partner-Attribution-Id' => '123123123'
          //'log.AdapterFactory' => '\PayPal\Log\DefaultLogFactory'
          // Factory class implementing \PayPal\Log\PayPalLogFactory
        )
      );

      // Create a payment by calling the 'create' method passing it a valid apiContext.
      // The return object contains the state and the url to which the buyer
      // must be redirected to for payment approval.
      try {
        $payment->create($apiContext);

        // Search for payment approved link.
        foreach ($payment->getLinks() as $link) {
          if ($link->getRel() == "approval_url") {
            $redirectUrl = $link->getHref();
          }
        }

        header('Location: ' . $redirectUrl);
        exit();

      } catch (\PayPal\Exception\PayPalConnectionException $e) {
        
        $message = "Unable to charge with PayPal at this time due to validation error.
        Please try again.";

        // Show error message to the user and save details in Drupal log.
        \Drupal::messenger()->addError(t($message));
        \Drupal::logger('paypal')->error($e->getData());
      }
    }
  }
}
