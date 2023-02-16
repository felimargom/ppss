<?php

namespace Drupal\ppss;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Exception\RequestException;

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
   * Constructs a WebhookCrudManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
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
    $subscription = $results[0];
    //calcular la fecha de vencimiento
    $status = 1;
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
   * @return array $data
   *   save data payment recurrent.
   */
  public function paymentCompleted($data) {
    //get data subscription
    $query = \Drupal::database()->select('ppss_sales', 's');
    $query->condition('id_subscription', $data->resource->billing_agreement_id);
    $query->fields('s', ['id','uid','frequency', 'status']);
    $results = $query->execute()->fetchAll();
    $subscription = $results[0];

    $query = \Drupal::database()->insert('ppss_sales_details');
    $query->fields(['sid', 'total', 'iva', 'created']);
    $query->values([
      $subscription->id,
      $data->resource->amount->total,
      0,
      \Drupal::time()->getRequestTime()
    ]);
    $query->execute();
  }

  
}