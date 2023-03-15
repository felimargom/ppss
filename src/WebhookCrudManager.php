<?php

namespace Drupal\ppss;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactory;

/**
 *
 * Entity CRUD operations in response to webhook notifications.
 *
 */
class WebhookCrudManager
{

  /**
   * The manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a WebhookCrudManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ClientInterface $http_client)
  {
    $this->entityTypeManager = $entity_type_manager;
    $this->httpClient = $http_client;
  }

  /**
   *
   * @return array $data
   *   cancel subscription.
   */
  public function cancelSubscription($id)
  {
    //obtener los datos de la venta
    $query = \Drupal::database()->select('ppss_sales', 's');
    $query->join('ppss_sales_details', 'sd', 's.id = sd.sid');
    $query->condition('id_subscription', $id);
    $query->fields('s', ['id','uid','frequency', 'status', 'id_role', 'mail']);
    $query->fields('sd',['id', 'created']);
    $query->orderBy('created', 'DESC');
    $results = $query->execute()->fetchAll();
    if (!count($results) > 0) {
      $subscription = $results[0]; // get the last payment
      // Validate supscription end date
      // o last payment date add +1 frecuency(month/year)
      $expire = strtotime(date('d-m-Y', $subscription->created). ' + 1 '.$subscription->frequency.'');
      $today = date('d-m-Y');
      // Validate expiration date with current expiration date
      if (date('d-m-Y', $expire) == $today) {
        // Update ppss_sales table
        \Drupal::database()->update('ppss_sales')->fields([
          'status' => 0,
          'expire' => $expire, ])->condition('id_subscription', $id, '=')->execute();
  
        // Remove user role added by subscription purchased
        try {
          $user = \Drupal\user\Entity\User::load($subscription->uid);
          $user->removeRole($subscription->id_role);
          $user->save();
          $msg_info = 'Se ha cancelado la suscripción de plan '.$subscription->id_role.
            ' del usuario '.$subscription->uid;
          $msg_user = 'Tu suscripción ha sido cancelada, ';
        } catch (\Exception $e) {
          \Drupal::logger('PPSS')->error($e->getMessage());
        }
      } else {
        // Update ppss_sales table
        \Drupal::database()->update('ppss_sales')->fields([
          'expire' => $expire,])->condition('id_subscription', $id, '=')->execute();
        $msg_info = 'Se ha programado con fecha '.date('d-m-Y', $expire).
          ' la cancelación de la suscripción de plan '.$subscription->id_role.
          ' del usuario '.$subscription->uid;
        $msg_user = 'Tu suscripción ha sido programada para cancelar el día '.date('d-m-Y', $expire);
      }
      // Validate role type 'enterprise' of 'Plan Negocio'
      if ($subscription->id_role == 'enterprise') {
        // Unpublish all ads setting a new date in the future
        $nids = \Drupal::entityQuery("node")->condition('uid', $subscription->uid)
          ->condition('type', 'nvi_anuncios_e')->execute();
        $entity = \Drupal::entityTypeManager()->getStorage("node");
        $nodes = $entity->loadMultiple($nids);
        foreach ($nodes as $node) {
          $node->unpublish_on = $expire;
          $node->save();
        }
      }
      \Drupal::logger('PPSS')->info($msg_info);
      $msg = '<div style="text-align: center;  margin: 20px;">
        <h1> ¡Hasta pronto! </h1>
        <h1> !Cancelación de suscripción en Encuéntralo! &#128522;</h1>
        <br><div style="text-align: center; font-size: 24px;">'.$msg_user.
        ' mientras tanto recuerda que puedes continuar publicando anuncios con la versión gratuita.
        </div><br><br>
        <div style="text-align: center; border-top: 1px solid #bdc1c6; padding-top: 20px; font-style: italic; font-size: medium; color: #83878c;">
        <br>--  El equipo de Encuéntralo</div></div>';

      // Send alert by email to stakeholders
      $module = 'ppss';
      $key = 'cancel_subscription';
      $to = $subscription->mail.";".\Drupal::config('system.site')->get('mail');
      $params['message'] = $msg;
      $params['subject'] = "Cancelación de suscripción - Encuéntralo";
      $langcode = \Drupal::currentUser()->getPreferredLangcode();
      $send = true;
      $result = \Drupal::service('plugin.manager.mail')->mail($module, $key, $to, $langcode, $params, NULL, $send);
      if (!$result['result']) {
        $msg_mail = t('There was a problem sending your message and it was not sent.');
      }
      else {
        $msg_mail = t('Your email has been sent.');
      }
      \Drupal::logger('PPSS')->info($msg_mail);
    }
  }

  /**
   * 
   * @param array $data
   *   save data payment recurrent.
   */
  public function paymentCompleted($data) {
    //get data subscription
    $query = \Drupal::database()->select('ppss_sales', 's');
    $query->condition('id_subscription', $data->resource->billing_agreement_id);
    $query->fields('s', ['id','uid','frequency', 'status', 'details']);
    $results = $query->execute()->fetchAll();
    $subscription = $results[0];
    $details = json_decode($subscription->details);
    
    try {
      $payment = \Drupal::database()->select('ppss_sales_details', 's')->condition('sid', $subscription->id)->condition('event_id', 0)->fields('s')->execute()->fetchAll();
      if(count($payment) == 1){
        \Drupal::database()->update('ppss_sales_details')->fields([
          'event_id' => $data->id,
        ])->condition('sid', $payment[0]->id, '=')->execute();
      } else {
        $query = \Drupal::database()->insert('ppss_sales_details');
        $query->fields(['sid', 'tax', 'price', 'total', 'created', 'event_id']);
        $query->values([
          $subscription->id,
          $details->plan->payment_definitions[0]->charge_models[0]->amount->value,
          $details->plan->payment_definitions[0]->amount->value,
          $data->resource->amount->total,
          strtotime($data->create_time),
          $data->id
        ]);
        $query->execute();
      }
    } catch (\Exception $e) {
      \Drupal::logger('PPSS')->error($e->getMessage());
    }
  }

  /**
   *
   * @param $id
   *   cancel subscription from encuentralo use the PayPal REST API server.
   */
  public function cancelSubscriptionE($id, $reason)
  {
    //validar que exista la suscripción y que este activa
    $query = \Drupal::database()->select('ppss_sales', 's');
    $query->condition('id', $id);
    $query->condition('status', 1);
    $query->isNull('expire');
    $query->fields('s');
    $result = $query->execute()->fetchAssoc();
    if($result) {
      $data = [];
      //razon de la cancelación
      $data['reason'] = $reason;
      try {
        //cancel subscription paypal
        $res = $this->httpClient->post($this->url_paypal().'/v1/billing/subscriptions/'.$result['id_subscription'].'/cancel', [
          'headers' => [ 
            'Authorization' => 'Bearer '.$this->accessToken().'',
            'Content-Type' => 'application/json'],
          'body' => json_encode($data),
        ]);
        //A successful request returns the HTTP 204 No Content status code
        if($res->getStatusCode() == 204) {
          return 'Suscripción cancelada';
        } else {
          return 'Error';
        }
      } catch (RequestException $e) {
        $exception = $e->getResponse()->getBody();
        $exception = json_decode($exception);
        return $exception->error ?? $exception->message;
      }
    } else {
      return 'La suscripción ya esta cancelada';
    }
  }

  /**
   *
   *  Get access token for use the PayPal REST API server.
   */
  private function accessToken()
  {
    $config = \Drupal::config('ppss.settings');
    $clientId = $config->get('client_id');
    $secret = $config->get('client_secret');
    $response = $this->httpClient->request('POST', $this->url_paypal().'/v1/oauth2/token', [
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'body' => 'grant_type=client_credentials',
      'auth' => [$clientId, $secret, 'basic']
    ]);
    $data = json_decode($response->getBody(), true);
    return $data['access_token'];
  }

  /**
   *
   *  Get url paypal for use the PayPal REST API server.
   */
  private function url_paypal()
  {
    $config = \Drupal::config('ppss.settings');
    if ($config->get('sandbox_mode') == TRUE)
    {
      return 'https://api-m.sandbox.paypal.com';
    } else {
      return 'https://api-m.paypal.com';
    }
  }
}