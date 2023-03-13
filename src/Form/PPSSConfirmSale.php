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
use PayPal\Api\Agreement;

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
    
    $apiContext = new ApiContext(
      new OAuthTokenCredential($clientId, $clientSecret)
    );

    if (!(is_null($node))) {
      $payment_id = \Drupal::request()->query->get('paymentId');
      $payer_id = \Drupal::request()->query->get('PayerID');
      $newRole = \Drupal::request()->query->get('roleid');
      $token = \Drupal::request()->query->get('token');

      if (!(is_null($payment_id))) {
        // Create a Payment object to confirm that the credentials do have the payment ID resolved.
        $objPayment = Payment::get($payment_id, $apiContext);

        // Create the payment run by invoking the class and extract the ID of the payer.
        $execution = new PaymentExecution();
        $execution->setPayerId($payer_id);

        // Validate with the credentials that the payer ID does match.
        $objPayment->execute($execution, $apiContext);
      
      } else {
        // It's recurring payment. A new agreement between the user and PayPal it's required.
        $objAgreement = new Agreement();
        
        try {
          // Execute agreement
          $objAgreement->execute($token, $apiContext);
          $objPayment = Agreement::get($objAgreement->getId(), $apiContext);

        } catch (\PayPal\Exception\PayPalConnectionException $ex) {
          dump($ex->getData());
        }
      }
     
      // Retrieve all the information of the sale
      $retrieveDataSale = $objPayment->toJSON();

      $datosUsuario = json_decode($retrieveDataSale);
      $email = $datosUsuario->payer->payer_info->email;
      $paymentPlatform = $datosUsuario->payer->payment_method;

      // It's recurring payment.
      if (is_null($payment_id)) {
        $plan = $datosUsuario->plan->payment_definitions;
        $frequency = $plan[0]->frequency;
        $interval = intval($plan[0]->frequency_interval);
      }

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
            $user->addRole($newRole);
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
            '#markup' => $this->t("Please login with your user account linked to this email: @email for begin use our services.", ['@email' => $email]),
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
            $user->addRole($newRole);
            $user->enforceIsNew();
            $user->log;
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

          // Send confirmation email.
          $result = array();
          $result = _user_mail_notify('register_no_approval_required', $user);

          if ((is_null($result)) or $result == false) {

            $message = t('There was a problem sending your email notification to @email.',
              array('@email' => $email));
            \Drupal::messenger()->addError($message);
            \Drupal::logger('PPSS')->error($message);
            $form['description'] = ['#markup' => $message,];

          } else {

            $form['description'] = [
              '#markup' => $this->t("Please review your email: @email to login details and begin use our services.", ['@email' => $email]),
            ];

          }
        
        }

        // Get the uid of the new user.
        $ids = \Drupal::entityQuery('user')
          ->condition('mail', $email)
          ->execute();
          
        $uid = intval(current($ids));

      } else {
        // Only will assign the role of the subscription
        // plan purchased to the current user
        $uid = \Drupal::currentUser()->id();
        try {
          $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id($uid));
          $user->addRole($newRole);
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
          '#markup' => $this->t("Your user account linked to this email: @email was successfully upgraded, please enjoy.", ['@email' => $email]),
        ];
      }

      // validate purchase register in ppss_sales
      $query = \Drupal::database()->select('ppss_sales', 's');
      $query->condition('id_subscription', $datosUsuario->id);
      $query->fields('s', ['id']);
      $count_result= count($query->execute()->fetchAll());
      if ($count_result == 0) {
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
          'status',
          'mail',
          'platform',
          'frequency',
          'frequency_interval',
          'details',
          'created',
          'id_subscription',
          'id_role'
        ]);

        // Set the values of the fields we selected.
        // Note that then must be in the same order as we defined them
        // in the $query->fields([...]) above.
        $query->values([
          $uid,
          1,
          $email,
          $paymentPlatform,
          $frequency,
          $interval,
          $retrieveDataSale,
          $currentTime,
          $datosUsuario->id,
          $newRole
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
        //get data subscription
        $query = \Drupal::database()->select('ppss_sales', 's');
        $query->condition('id_subscription', $datosUsuario->id);
        $query->fields('s', ['id']);
        $results = $query->execute()->fetchAll();
        $subscription = $results[0];
        $total = $datosUsuario->plan->payment_definitions[0]->charge_models[0]->amount->value + $datosUsuario->plan->payment_definitions[0]->amount->value;
        //Save all transaction data in ppss_sales_details
        try {
          $query = \Drupal::database()->insert('ppss_sales_details');
          $query->fields(['sid', 'tax', 'price', 'total', 'created', 'event_id']);
          $query->values([
            $subscription->id,
            $datosUsuario->plan->payment_definitions[0]->charge_models[0]->amount->value,
            $datosUsuario->plan->payment_definitions[0]->amount->value,
            $total,
            $currentTime,
            0
          ]);
          $query->execute();
        } catch (\Exception $e) {
          \Drupal::logger('PPSS')->error($e->getMessage());
        }
        \Drupal::logger('PPSS')->info('Se ha registrado el primer pago de la suscripción: '.$datosUsuario->id);
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