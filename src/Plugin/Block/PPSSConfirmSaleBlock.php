<?php

/**
 * @file
 * Creates a block which displays the PPSSConfirmSale contained in PPSSConfirmSale.php
 */

namespace Drupal\ppss\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Provides the PPSS Confirm Sale main block.
 *
 * @Block(
 *   id = "ppss_confirm_sale",
 *   admin_label = @Translation("PPSS Confirm Sale Block")
 * )
 */
class PPSSConfirmSaleBlock extends BlockBase
{
  /**
   * {@inheritdoc}
   */
  public function build()
  {
    return \Drupal::formBuilder()->getForm('Drupal\ppss\Form\PPSSConfirmSale');
  }
  
  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account)
  {
    // If viewing a node, get the fully loaded node object.
    $node = \Drupal::routeMatch()->getParameter('node');

    if (!(is_null($node))) {
      $request = \Drupal::request();
      $requestUri = $request->getRequestUri();
      $config = \Drupal::config('ppss.settings');
      $successUrl = $config->get('success_url');

      if (strchr($requestUri, $successUrl)) {
        return AccessResult::allowedIfHasPermission($account, 'view ppss button');
      }
      
    }

    return AccessResult::forbidden();
  }
}
