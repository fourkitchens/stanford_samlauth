<?php

declare(strict_types=1);

namespace Drupal\stanford_samlauth\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\stanford_samlauth\Service\WorkgroupApiInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the Valid SunetID constraint.
 */
final class ValidSunetIDConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs the object.
   */
  public function __construct(private readonly WorkgroupApiInterface $workgroupApi) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self($container->get('stanford_samlauth.workgroup_api'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $item, Constraint $constraint): void {
    if ($item && $this->workgroupApi->connectionSuccessful() && !$this->workgroupApi->isSunetValid($item)) {
      $this->context->addViolation($constraint->message);
    }
  }

}
