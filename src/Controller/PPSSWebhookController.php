<?php

/**
 * @file
 * Primarily serves as a gatekeeper that receives incoming notifications, determines
 * whether or not to act on them (via the authorize method), and then shuttles them
 * along to their final destination.
 */

namespace Drupal\ppss\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Component\Utility\Html;
use Drupal\Core\TypedData\DataReferenceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defines a controller for managing webhook notifications.
 */
class PPSSWebhookController extends ControllerBase
{

  /**
   * The HTTP request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The queue factory.
   *
   * @var Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Constructs a PPSSWebhookController object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *  The HTTP request object.
   * @param Drupal\Core\Queue\QueueFactory $queue
   *  The queue factory.
   */
  public function __construct(Request $request, QueueFactory $queue)
  {
    $this->request = $request;
    $this->queueFactory = $queue;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
        $container->get('request_stack')->getCurrentRequest(),
        $container->get('queue'),
      );
  }

  /**
   * Listens for webhook notifications and queues them for processing.
   *
   * @return Symfony\Component\HttpFoundation\Response
   *   Webhook providers typically expect an HTTP 200 (OK) response.
   */
  public function listener()
  {
    // Prepare the response.
    $response = new Response();
    $response->setContent('Notification received');

    // Capture the contents of the notification (payload).
    $payload = $this->request->getContent();
    
    // Get the queue implementation.
    $queue = $this->queueFactory->get('ppss_webhook_processor');

    // Add the $payload to the queue.
    $queue->createItem($payload);

    // Respond with the success message.
    return $response;
  }

  /**
   * Checks access for incoming webhook notifications.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access()
  {
    $config = \Drupal::config('ppss.settings');
    // Event header validation format expected from PayPal.
    // Ref.:https://developer.paypal.com/api/rest/webhooks/
    // https://stackoverflow.com/questions/61041128/php-verify-paypal-webhook-signature

    
    $transmissionId = $this->request->headers->get('paypal-transmission-id');
    $timeStamp = $this->request->headers->get('paypal-transmission-time');

    // Capture the contents of the notification (payload).
    $payload = $this->request->getContent();
    $dataReceived = json_decode($payload);
    // Get the ID of the webhook resource for the destination URL to which PayPal
    // delivers the event notification.
    $webhookId = $config->get('webhook_id');

    // Get the signature from the headers.
    $transmissionSig = $this->request->headers->get('paypal-transmission-sig');
    $paypalCertUrl = $this->request->headers->get('paypal-cert-url');

    // data: <transmissionId>|<timeStamp>|<webhookId>|<crc32>
    $verifyAccess = openssl_verify(
      data: implode(separator: '|', array:
      [
        $transmissionId,
        $timeStamp,
        $webhookId,
        crc32(string: $payload),
      ]),
      signature: base64_decode(string: $transmissionSig),
      public_key: openssl_pkey_get_public(public_key: file_get_contents(filename: $paypalCertUrl)),
      algorithm: 'sha256WithRSAEncryption',
    );

    if ($verifyAccess === 1) {
      $accessAllowed = true;
    } elseif ($verifyAccess === 0) {
      $accessAllowed = false;
    } else {
      \Drupal::logger('PPSS')->error('Error checking signature');
      $accessAllowed = false;
    }

    // If they validation was successful, allow access to the route.
    return AccessResult::allowedIf($verifyAccess); // Please review, validation don't work.
  }

  /**
   * Cancel subscription from encuentralo.
   *
   * @param $id
   * id subscrition
   */
  public function cancel_subscription($id){
    //llamar al servicio
    $cancel = \Drupal::service('ppss.webhook_crud')->cancelSubscriptionE($id);
    $this->messenger()->addWarning($cancel);
    return [
      '#type' => 'markup',
      '#markup' => "$cancel"
    ];
  }
}
