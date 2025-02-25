<?php

declare(strict_types=1);

namespace Drupal\stanford_samlauth\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;
use Drupal\Core\Validation\Attribute\Constraint;

/**
 * Provides a Valid SunetID constraint.
 */
#[Constraint(
  id: 'ValidSunetID',
  label: new TranslatableMarkup('Valid SunetID', [], ['context' => 'Validation']),
)]
final class ValidSunetIDConstraint extends SymfonyConstraint {

  public string $message = '@sunetid is not a valid SunetID.';

}
