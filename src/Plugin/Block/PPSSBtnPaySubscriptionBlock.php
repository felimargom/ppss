<?php

/**
 * @file
 * Creates a block which displays the PPSSButtonPay contained in PPSSButtonPay.php
 */

namespace Drupal\ppss\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\ppss\Form\PPSSBtnPaySubscription;

/**
 * Provides the PPSS pay subscription block.
 *
 * @Block(
 *   id = "btn_pay_subscription",
 *   admin_label = @Translation("Button pay of subscription")
 * )
 */
class PPSSBtnPaySubscriptionBlock extends BlockBase
{
  /**
   * {@inheritdoc}
   */
  public function build()
  {
    $form = new PPSSBtnPaySubscription;
    $form = \Drupal::formBuilder()->getForm('Drupal\ppss\Form\PPSSBtnPaySubscription');
    
    // Takes the block title and prints inside the payment button like
    // call to action text.
    $form['submit']['#value'] = $this->configuration["label"];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account)
  {
    // If viewing a node, get the fully loaded node object.
    $node = \Drupal::routeMatch()->getParameter('node');

    // Only shows button in allowed node types.
    if (!(is_null($node))) {
      $nodeType = $node->getType();
      $allowedNodeTypes = \Drupal::config('ppss.settings')->get('content_types');
      $findedNodeType = array_search($node->getType(), $allowedNodeTypes);

      if ($nodeType == $findedNodeType) {
        return AccessResult::allowedIfHasPermission($account, 'view ppss button');
      }

    }

    return AccessResult::forbidden();

  }
}
