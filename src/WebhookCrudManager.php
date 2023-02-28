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
class WebhookCrudManager {

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
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ClientInterface $http_client) {
    $this->entityTypeManager = $entity_type_manager;
    $this->httpClient = $http_client;
  }

  /**
   * 
   * @return array $data
   *   cancel subscription.
   */
  public function cancelSubscription($id) {
    //obtener los datos de la venta
    $query = \Drupal::database()->select('ppss_sales', 's');
    $query->leftJoin('ppss_sales_details', 'sd', 's.id = sd.sid');
    $query->condition('id_subscription', $id);
    $query->fields('s', ['id','uid','frequency', 'status']);
    $query->fields('sd',['created']);
    $query->orderBy('created', 'DESC');
    $results = $query->execute()->fetchAll();
    $subscription = $results[0];//get the last payment
    //calcular la fecha de vencimiento
    $status = 1;
    //a la ultima fecha de pago sumar +1 frecuencia(mes/año)
    $expire = strtotime(date('d-m-Y',$subscription->created). ' + 1 '.$subscription->frequency.'');
    $today = date('d-m-Y');
    \Drupal::logger('PPSS')->error($expire);
    if(date('d-m-Y',$expire) == $today) {
      $status = 0;
    }
    //actualizar la tabla de ppss_sales
    \Drupal::database()->update('ppss_sales')->fields([
      'status' => $status,
      'expire' => $expire,
    ])->condition('id_subscription', $id, '=')->execute();

    //validar el tipo de rol

    //despublicar anuncios
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
    $query->fields('s', ['id','uid','frequency', 'status']);
    $results = $query->execute()->fetchAll();
    $subscription = $results[0];

    try {
      $query = \Drupal::database()->insert('ppss_sales_details');
      $query->fields(['sid', 'total', 'iva', 'created', 'event_id']);
      $query->values([
        $subscription->id,
        $data->resource->amount->total,
        0,
        strtotime($data->create_time),
        $data->id
      ]);
      $query->execute();
    } catch (\Exception $e) {
      \Drupal::logger('PPSS')->error($e->getMessage());
    }
  }

  /**
   * 
   * @param $id
   *   cancel subscription from encuentralo.
   */
  public function cancelSubscriptionE($id, $reason) {
    //validar que exista la suscripción y que este activa
    $query = \Drupal::database()->select('ppss_sales', 's');
    $query->condition('id_subscription', $id);
    $query->condition('status', 1);
    $query->fields('s');
    $results = $query->execute()->fetchAll();
    if(count($results) > 0) {
      $data = [];
      //razon de la cancelación
      $data['reason'] = $reason;
      try {
        //cancel subscription paypal
        $res = $this->httpClient->post($this->url_paypal().'/v1/billing/subscriptions/'.$id.'/cancel', [
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
  private function accessToken() {
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

  private function url_paypal() {
    $config = \Drupal::config('ppss.settings');
    if ($config->get('sandbox_mode') == TRUE) {
      return 'https://api-m.sandbox.paypal.com';
    } else {
      return 'https://api-m.paypal.com';
    }
  }

}