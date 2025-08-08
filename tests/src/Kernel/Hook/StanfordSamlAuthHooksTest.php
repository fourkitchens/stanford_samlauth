<?php

declare(strict_types=1);

namespace Drupal\Tests\stanford_samlauth\Kernel\Hook;

use Drupal\Tests\stanford_samlauth\Kernel\StanfordSamlAuthTestBase;
use Drupal\user\Form\UserLoginForm;

/**
 * Samlauth Hooks.
 */
class StanfordSamlAuthHooksTest extends StanfordSamlAuthTestBase {

  public function testLocalTasks() {
    $config = $this->container->get('config.factory')
      ->getEditable('stanford_samlauth.settings');

    $tasks = $this->container->get('plugin.manager.menu.local_task')
      ->getLocalTasks('user.login');
    $this->assertArrayHasKey('tabs', $tasks);
  }

    public function testUserLoginForm() {
      $user = $this->container->get('entity_type.manager')->getStorage('user')
        ->create([]);
      $this->assertTrue($user->hasField('affiliation'));

      $form_builder = $this->container->get('form_builder');
      $config = $this->container->get('config.factory')
        ->getEditable('stanford_samlauth.settings');

      $form = $form_builder->getForm(UserLoginForm::class);
      $this->assertArrayNotHasKey('name', $form);
      $this->assertArrayNotHasKey('pass', $form);
      $this->assertArrayNotHasKey('actions', $form);
      $this->assertArrayNotHasKey('manual', $form);

      $config->set('hide_local_login', FALSE)->save();

      $form = $form_builder->getForm(UserLoginForm::class);
      $this->assertArrayHasKey('manual', $form);
      $this->assertArrayHasKey('name', $form['manual']);
      $this->assertArrayHasKey('pass', $form['manual']);
      $this->assertArrayHasKey('actions', $form['manual']);
    }

}
