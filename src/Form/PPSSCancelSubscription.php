<?php

namespace Drupal\PPSS\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides PPSS cancel subscription.
 */
class PPSSCancelSubscription extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ppss_cancel_subscription_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    $form['reason'] = [
      '#type' => 'select',
      '#title' => 'Razón de la cancelación',
      '#required' => TRUE,
      '#options' => [
        1 => 'La navegación en el sitio web es difícil.',
        2 => 'El precio del plan es elevado. ',
        4 => 'Me cambié a otra plataforma',
        5 => 'Otro',
      ],
      '#ajax' => [
        'callback' => '::otherField',
        'wrapper' => 'container',
      ],
    ];
    $form['id'] = [
      '#type' => 'hidden',
      '#required' => TRUE,
      '#default_value' => $id,
      '#description' => 'ID de la suscripcion'
    ];
    $form['container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'container'
      ],
    ];
    if ($form_state->getValue('reason', NULL) === "5") {
      $form['container']['other'] = [
        '#type' => 'textfield',
        '#title' => 'Especificar razón',
        '#required' => TRUE,
      ];
    }
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  public function otherField($form, FormStateInterface $form_state) {
    return $form['container'];
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $reason = $form['reason']['#options'][$form_state->getValue('reason')];
    $id = $form_state->getValue('id');
    if($form_state->getValue('reason') == '5') {
      $reason = $form_state->getValue('other');
    }
    //llamar al servicio
    $cancel = \Drupal::service('ppss.webhook_crud')->cancelSubscriptionE($id, $reason);
    $this->messenger()->addWarning($cancel);
  }

}