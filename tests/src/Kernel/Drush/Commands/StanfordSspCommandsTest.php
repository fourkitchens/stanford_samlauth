<?php

namespace Drupal\Tests\stanford_samlauth\Kernel\Drush\Commands;

use Drupal\stanford_samlauth\Drush\Commands\StanfordSamlAuthCommands;
use Drupal\stanford_samlauth\Service\WorkgroupApiInterface;
use Drupal\Tests\stanford_samlauth\Kernel\StanfordSamlAuthTestBase;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class StanfordSspCommandsTest
 *
 * @package Drupal\Tests\stanford_samlauth\Kernel\Commands
 * @coversDefaultClass \Drupal\stanford_samlauth\Drush\Commands\StanfordSamlAuthCommands
 */
class StanfordSspCommandsTest extends StanfordSamlAuthTestBase {

  /**
   * Drush command service.
   *
   * @var \Drupal\stanford_samlauth\Drush\Commands\StanfordSamlAuthCommands
   */
  protected $commandObject;

  /**
   * Workgroup API flag if the sunet is valid.
   *
   * @var bool
   */
  protected $isValidSunet = TRUE;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setup();

    $authmap = \Drupal::service('externalauth.authmap');
    $form_builder = \Drupal::formBuilder();
    $config_factory = \Drupal::configFactory();
    $this->commandObject = new StanfordSamlAuthCommands($authmap, $form_builder, $config_factory);
    $this->commandObject->setLogger(\Drupal::logger('stanford_samlauth'));
    $this->commandObject->setOutput($this->createMock(OutputInterface::class));

    $workgroup_api = $this->createMock(WorkgroupApiInterface::class);
    $workgroup_api->method('connectionSuccessful')->willReturn(TRUE);
    $workgroup_api->method('isSunetValid')
      ->willReturnReference($this->isValidSunet);
    \Drupal::getContainer()
      ->set('stanford_samlauth.workgroup_api', $workgroup_api);
  }

  /**
   * Test adding a new role mapping.
   */
  public function testAddRoleMapping() {
    // Role doesn't exist.
    $this->commandObject->entitlementRole($this->randomMachineName(), $this->randomMachineName());
    $this->assertEmpty(\Drupal::config('stanford_samlauath.settings')
      ->get('role_mapping.mapping'));

    // Role doesn't exist.
    $this->commandObject->entitlementRole($this->randomMachineName(), $this->randomMachineName());
    $this->assertEmpty(\Drupal::config('stanford_samlauath.settings')
      ->get('role_mapping.mapping'));

    // Role exists.
    $workgroup = $this->randomMachineName();
    $this->commandObject->entitlementRole($workgroup, 'role1');

    $this->assertEquals([
      'role' => 'role1',
      'attribute' => 'eduPersonEntitlement',
      'value' => $workgroup,
    ], \Drupal::config('stanford_samlauth.settings')
      ->get('role_mapping.mapping.0'));
  }

  /**
   * Create a user through drush.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testAddingUser() {
    $sunet = strtolower($this->randomMachineName());
    $options = [
      'email' => $this->randomMachineName() . '@' . $this->randomMachineName() . '.com',
      'roles' => '',
    ];

    $user = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['name' => $sunet]);
    $this->assertEmpty($user);
    /** @var \Drupal\externalauth\Authmap $authmap */
    $authmap = \Drupal::service('externalauth.authmap');
    $this->assertFalse($authmap->getUid(strtolower($sunet), 'samlauth'));

    $this->commandObject->addUser($sunet, $options);

    // Make sure user entity was created.
    $user = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['name' => $sunet]);
    $this->assertNotEmpty($user);
    $this->assertNotFalse($authmap->getUid(strtolower($sunet), 'samlauth'));
  }

  public function testInvalidSunet() {
    $sunet = 'foo bar';
    $options = ['email' => '', 'roles' => ''];

    $this->expectException('\Exception');
    $this->commandObject->addUser($sunet, $options);
  }

  public function testInvalidWorkgroupApiSunet() {
    $this->isValidSunet = FALSE;
    $sunet = 'foobar';
    $options = ['email' => '', 'roles' => ''];

    $this->expectException('\Exception');
    $this->commandObject->addUser($sunet, $options);
  }

  public function testUserExists() {
    $sunet = 'foobar';
    $options = ['email' => '', 'roles' => ''];
    $this->commandObject->addUser($sunet, $options);

    $this->expectException('\Exception');
    $this->commandObject->addUser($sunet, $options);
  }

}
