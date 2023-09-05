<?php

namespace Drupal\stanford_samlauth\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Stanford SAML Authentication settings for this site.
 */
class SamlAuthAuthorizationsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stanford_samlauth_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['stanford_samlauth.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('stanford_samlauth.settings');
    $form['restrict'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Restrict access'),
      '#default_value' => $config->get('allowed.restrict'),
    ];
    $form['users'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed Users'),
      '#default_value' => implode(',', $config->get('allowed.users')),
      '#states' => [
        'visible' => [':input[name=restrict]' => ['checked' => TRUE]],
      ],
    ];
    $form['affiliations'] = [
      '#type' => 'select',
      '#title' => $this->t('Allowed Users'),
      '#multiple' => TRUE,
      '#options' => [
        'affiliate' => $this->t('Affiliates'),
        'staff' => $this->t('Staff'),
        'student' => $this->t('Students'),
        'faculty' => $this->t('Faculty'),
        'member' => $this->t('Members'),
      ],
      '#default_value' => $config->get('allowed.affiliations') ?? [],
      '#states' => [
        'visible' => [':input[name=restrict]' => ['checked' => TRUE]],
      ],
    ];
    $form['groups'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed Users'),
      '#default_value' => implode(',', $config->get('allowed.groups')),
      '#states' => [
        'visible' => [':input[name=restrict]' => ['checked' => TRUE]],
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // If not restricting, clear the input field values.
    if (!$form_state->getValue('restrict')) {
      $form_state->setValue('users', '')
        ->setValue('affiliations', [])
        ->setValue('groups', '');
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $users = array_filter(explode(',', preg_replace('/[^a-z\d,]/', '', strtolower($form_state->getValue('users')))));
    $groups = array_filter(explode(',', str_replace(', ', ',', $form_state->getValue('groups'))));

    $this->config('stanford_samlauth.settings')
      ->set('allowed.restrict', $form_state->getValue('restrict'))
      ->set('allowed.users', $users)
      ->set('allowed.affiliations', array_values($form_state->getValue('affiliations')))
      ->set('allowed.groups', $groups)
      ->save();
    parent::submitForm($form, $form_state);
  }

}
