<?php

/**
 * @file
 * Creates a block which displays the PPSSButtonPay contained in PPSSButtonPay.php
 */

namespace Drupal\ppss\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Provides the PPSS main block.
 *
 * @Block(
 *   id = "ppss_button_pay",
 *   admin_label = @Translation("The PPSS Button Pay")
 * )
 */
class PPSSButtonPayBlock extends BlockBase
{
  /**
   * {@inheritdoc}
   */
  public function build()
  {

    return \Drupal::formBuilder()->getForm('Drupal\ppss\Form\PPSSButtonPay');

  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account)
  {
    // If viewing a node, get the fully loaded node object.
    $node = \Drupal::routeMatch()->getParameter('node');

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
