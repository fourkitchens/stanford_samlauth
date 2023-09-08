<?php

namespace Drupal\Tests\stanford_samlauth\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\stanford_samlauth\Drush\Commands\StanfordSamlAuthCommands;
use Drupal\user\Entity\Role;
use Symfony\Component\Console\Output\OutputInterface;

class StanfordSamlAuthTestBase extends KernelTestBase {

  /**
   * {@inheritDoc}
   */
  protected static $modules = [
    'system',
    'stanford_samlauth',
    'samlauth',
    'externalauth',
    'user',
    'stanford_samlauth_test',
    'path_alias',
  ];

  /**
   * {@inheritDoc}
   */
  public function setup(): void {
    parent::setup();

    $this->installEntitySchema('user');
    $this->installEntitySchema('user_role');
    $this->installSchema('externalauth', 'authmap');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['stanford_samlauth']);

    for ($i = 0; $i < 5; $i++) {
      Role::create(['label' => "Role $i", 'id' => "role$i"])->save();
    }
  }

}
