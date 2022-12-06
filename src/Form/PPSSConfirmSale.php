<?php

/**
 * @file
 * A form to collect the response where buyer must be redirected to after
 * payment approval.
 */

namespace Drupal\ppss\Form;

use Drupal;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;

 /**
  * Provides an RSVP Email form.
  */
class PPSSConfirmSale extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'ppss_form_sale';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    // Atempt to get the fully loaded node object of the viewed page.
    $node = \Drupal::routeMatch()->getParameter('node');
    $config = \Drupal::config('ppss.settings');
    $clientId = $config->get('client_id');
    $clientSecret = $config->get('client_secret');
    
    if ( !(is_null($node)) ) {
      $payment_id = \Drupal::request()->query->get('paymentId');
      $payer_id = \Drupal::request()->query->get('PayerID');

      $apiContext = new ApiContext(
        new OAuthTokenCredential($clientId, $clientSecret)
      );

      // Create a Payment object to confirm that the credentials do have the payment ID resolved.
      $objPayment = Payment::get($payment_id, $apiContext);

      // Create the payment run by invoking the class and extract the ID of the payer.
      $execution = new PaymentExecution();
      $execution->setPayerId($payer_id);

      // Validate with the credentials that the payer ID does match.
      $objPayment->execute($execution, $apiContext);
    
      // Retrieve all the information of the sale
      $retrieveDataSale = $objPayment->toJSON();
      
      $datosUsuario = json_decode($retrieveDataSale);
      $email = $datosUsuario->payer->payer_info->email;
      $paymentPlatform = $datosUsuario->payer->payment_method;

      $isAnonymous = \Drupal::currentUser()->isAnonymous();

      if ($isAnonymous) {
        //  It's an anonymous user. First will search about if returned email by
        //  PayPal exist, if not, trying to create an account with data returned.
        
        $ids = \Drupal::entityQuery('user')
          ->condition('mail', $email)
          ->execute();

        // Find if email exist.
        if (!empty($ids)) {
          // This mail already exists. Only will assign the role of the subscription
          // plan purchased.
          $uid = intval(current($ids));
          try {
            $user = \Drupal\user\Entity\User::load($uid);
            $user->addRole('nvi_suscriber');
            $user->save();
          } catch (\Exception $e) {
            $errorInfo = t('Charge was made correctly but something was wrong when trying
              to assign the new subscription plan to your account. Please contact
              with the site administrator and explain this situation.');
            // Show error message to the user
            \Drupal::messenger()->addError($errorInfo);
            \Drupal::logger('Sales')->error($errorInfo);
            \Drupal::logger('PPSS')->error($e->getMessage());
          }
          $form['description'] = [
            '#markup' => $this->t("Please login with your user account linked to this email:
              $email for begin use our services."),
          ];
        } else {
          // Creates a new user with the PayPal email.
          try {
            // Get te user name to register from the email
            $temp = explode("@", $email);
            $userName = $temp[0];
            $user = User::create();
            $user->set('status', 1);
            $user->setEmail($email);
            $user->setUsername($userName);
            $user->addRole('nvi_suscriber');
            $user->save();
          } catch (\Exception $e) {
            $errorInfo = t('Charge was made correctly but something was wrong when trying
              to create your account. Please contact with the site administrator
              and explain this situation.');
              // Show error message to the user
              \Drupal::messenger()->addError($errorInfo);
              \Drupal::logger('Sales')->error($errorInfo);
              \Drupal::logger('PPSS')->error($e->getMessage());
            }

          $form['description'] = [
            '#markup' => $this->t("Please review your email: $email to login details
              and begin use our services."),
          ];

          // Get the uid of the new user.
          $ids = \Drupal::entityQuery('user')
            ->condition('mail', $email)
            ->execute();
          
          $uid = intval(current($ids));
        }
      } else {
        // Only will assign the role of the subscription
        // plan purchased to the current user
        $uid = \Drupal::currentUser()->id();
        try {
          $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id($uid));
          $user->addRole('nvi_suscriber');
          $user->save();
        } catch (\Exception $e) {
          // Show error message to the user
          $errorInfo = t('Charge was made correctly but something was wrong when trying
            to assign the new subscription plan to your account. Please contact
            with the site administrator and explain this situation.');
          \Drupal::messenger()->addError($errorInfo);
          \Drupal::logger('Sales')->error($errorInfo);
          \Drupal::logger('PPSS')->error($e->getMessage());
        }
        $form['description'] = [
          '#markup' => $this->t("Your user account linked to this email:
            $email was successfully upgraded, please enjoy."),
        ];
      }

      // Save all transaction data in DB for future reference.
      try {
        // Initiate missing variables to save.
        $currentTime = \Drupal::time()->getRequestTime();

        // Save the values to the database

        // Start to build a query builder object $query.
        // Ref.: https://www.drupal.org/docs/drupal-apis/database-api/insert-queries
        $query = Drupal::database()->insert('ppss_sales');

        // Specify the fields taht the query will insert to.
        $query->fields([
          'uid',
          'mail',
          'platform',
          'details',
          'created',
        ]);

        // Set the values of the fields we selected.
        // Note that then must be in the same order as we defined them
        // in the $query->fields([...]) above.
        $query->values([
          $uid,
          $email,
          $paymentPlatform,
          $retrieveDataSale,
          $currentTime,
        ]);

        // Execute the query!
        // Drupal handles the exact syntax of the query automatically
        $query->execute();

        // Provide the form submitter a nice message.
        \Drupal::messenger()->addMessage(t('Successful subscription purchase.'));
      } catch (\Exception $e) {
        // Show error message to the user
        $errorInfo = t('Unable to save payment to DB at this time due to database error.
          Please contact with the site administrator and explain this situation.');
        \Drupal::messenger()->addError($errorInfo);
        \Drupal::logger('Sales')->error($errorInfo);
        \Drupal::logger('PPSS')->error($e->getMessage());
      }

    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    // This method was not used.
  }
}
