<?php

namespace Drupal\stanford_samlauth\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConstraintFieldValidationRuleBase;

/**
 * Provides functionality for ValidSunetIDValidation.
 *
 * @FieldValidationRule(
 *   id = "valid_sunetid_constraint_rule",
 *   label = @Translation("Valid SunetID"),
 *   description = @Translation("Validate string is valid sunet.")
 * )
 */
class ValidSunetIDFieldValidationRule extends ConstraintFieldValidationRuleBase {

  /**
   * {@inheritdoc}
   */
  public function getConstraintName(): string {
    return "ValidSunetID";
  }

  /**
   * {@inheritdoc}
   */
  public function isPropertyConstraint(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['message' => 'This is not a valid SunetID.'] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration + parent::getConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message'),
      '#default_value' => $this->configuration['message'],
      '#maxlength' => 255,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['message'] = $form_state->getValue('message');
  }

}
