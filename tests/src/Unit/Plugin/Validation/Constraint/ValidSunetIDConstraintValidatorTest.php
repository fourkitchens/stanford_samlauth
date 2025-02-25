<?php

namespace Drupal\Tests\stanford_samlauth\Unit\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\stanford_samlauth\Plugin\Validation\Constraint\ValidSunetIDConstraint;
use Drupal\stanford_samlauth\Plugin\Validation\Constraint\ValidSunetIDConstraintValidator;
use Drupal\stanford_samlauth\Service\WorkgroupApiInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @coversDefaultClass \Drupal\stanford_samlauth\Plugin\Validation\Constraint\ValidSunetIDConstraintValidator
 */
class ValidSunetIDConstraintValidatorTest extends UnitTestCase {

  protected $plugin;

  protected function setUp(): void {
    parent::setUp();

    $workgroup_api = $this->createMock(WorkgroupApiInterface::class);
    $workgroup_api->method('connectionSuccessful')->willReturn(TRUE);
    $workgroup_api->method('isSunetValid')->willReturn(FALSE);

    $container = new ContainerBuilder();
    $container->set('stanford_samlauth.workgroup_api', $workgroup_api);
    $this->plugin = ValidSunetIDConstraintValidator::create($container);

    $context = $this->createMock(ExecutionContextInterface::class);
    $this->plugin->initialize($context);
  }

  public function testValidation() {
    $constraint = new ValidSunetIDConstraint();
    $this->assertNull($this->plugin->validate('foobar', $constraint));
  }

}
