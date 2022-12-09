<?php

/**
 * @file
 * Settings for administering the Roles links with his SKUs form.
 */

namespace Drupal\ppss\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;

class PPSSFormRolesSettings extends ConfigFormBase
{
  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    // Unique ID of the form.
    return 'ppss_admin_roles_skus';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames()
  {
    return [
      'ppss.settings',
    ];
  }
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    // The settings needed was configured inside ppss.settings.yml file.
    $config = $this->config('ppss.settings');

    $form['init_message'] = [
      '#markup' => $this->t('Relacione el rol que será asignado al usuario cuando compre el SKU inidcado.'),
    ];

    // Set the roles for the dropdown box
    $roles = Role::loadMultiple();
    $rolesNames = array(t('--- SELECT ---'));

    foreach ($roles as $data) {
      $rolesNames[] = $data->id();
    }

    $form['role_options'] = array(
      '#type' => 'value',
      '#value' => $rolesNames,
    );

    $form['roles_names'] = array(
      '#type' => 'select',
      '#description' => "Select the assigned role.",
      '#options' => $form['role_options']['#value'],
    );
    
    // Create table header
    $header_table = array(
      'sku' =>    $this->t('SKU'),
      'user_role' => $this->t('Role'),
    );
    
    // Select records from table
    $query = \Drupal::database()->select('node__field_sku', 's');
    $query->condition('bundle', 'mt_product');
    $query->fields('s', ['field_sku_value']);
    $results = $query->execute()->fetchAll();
    $skus = array();
    foreach ($results as $data) {

      // Print the data from table
      $skus[] = array(
        'sku' => $data->field_sku_value,
      );
    }

    // Display data in site
    $form['table'] = [
      '#type' => 'table',
      '#header' => $header_table,
      '#rows' => $skus,
      '#empty' => $this->t("Can't find any SKU"),
    ];

    return parent::buildForm($form, $form_state);
  }
}