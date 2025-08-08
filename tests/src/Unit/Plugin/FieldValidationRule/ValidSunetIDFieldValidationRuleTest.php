<?php

namespace Drupal\Tests\stanford_samlauth\Unit\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Token;
use Drupal\stanford_samlauth\Plugin\FieldValidationRule\ValidSunetIDFieldValidationRule;
use Drupal\Tests\UnitTestCase;

/**
 * Field validation test.
 */
class ValidSunetIDFieldValidationRuleTest extends UnitTestCase {

  protected $validationRule;

  protected function setUp(): void {
    parent::setUp();

    $logger_channel = $this->createMock(LoggerChannelInterface::class);

    $logger = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger->method('get')->willReturn($logger_channel);

    $container = new ContainerBuilder();
    $container->set('logger.factory', $logger);
    $container->set('token', $this->createMock(Token::class));
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $this->validationRule = ValidSunetIDFieldValidationRule::create($container, [], 'foo', []);
  }

  public function testFieldValidation() {
    $this->assertEquals('ValidSunetID', $this->validationRule->getConstraintName());
    $this->assertTrue($this->validationRule->isPropertyConstraint());

    $form = [];
    $form_state = new FormState();
    $this->assertArrayHasKey('message', $this->validationRule->buildConfigurationForm($form, $form_state));

    $form_state->setValue('message', 'foobarbaz');
    $this->validationRule->submitConfigurationForm($form, $form_state);

    $this->assertEquals('foobarbaz', $this->validationRule->getConfiguration()['message']);
  }

}
