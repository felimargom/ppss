<?php

/**
 * @file
 * Process a queue of webhook notification payload data in listener() contained in
 * PPSSWebhookController.php
 */

namespace Drupal\ppss\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\webhook_entities\WebhookUuidLookup;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ppss\WebhookCrudManager;

/**
 * Process a queue of webhook notification payload data.
 *
 * @QueueWorker(
 *   id = "ppss_webhook_processor",
 *   title = @Translation("PPSS Webhook notification processor"),
 *   cron = {"time" = 30}
 * )
 */
class PPSSWebhookQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  
  /**
   * WebhookCrudManager service.
   *
   * @var \Drupal\ppss\WebhookCrudManager
   */
  protected $crudManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, WebhookCrudManager $crudManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->crudManager = $crudManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
    $configuration,
    $plugin_id,
    $plugin_definition,
    $container->get('ppss.webhook_crud')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($payload)
  {
    // Only process the payload if it contains data.
    if (!empty($payload)) {

      // Decode the JSON payload to a PHP object.
      $entity_data = json_decode($payload);
      
      switch($entity_data->event_type) {
        case 'BILLING.SUBSCRIPTION.CANCELLED':
          //A billing subscription was cancelled
          $this->crudManager->cancelSubscription($entity_data->resource->id);
          break;

        case 'PAYMENT.SALE.COMPLETED':
          //A payment completed
          $this->crudManager->paymentCompleted($entity_data);
          break;

        case 'BILLING.PLAN.CREATED':
          //A billing plan was created
          $this->crudManager->create();
          break;
      }
    } else {
      \Drupal::logger('PPSS')->error('Nada que procesar');
    }
  }
}
