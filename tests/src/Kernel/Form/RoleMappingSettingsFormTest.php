<?php

namespace Drupal\Tests\stanford_samlauth\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\stanford_samlauth\Form\RoleMappingSettingsForm;
use Drupal\Tests\stanford_samlauth\Kernel\StanfordSamlAuthTestBase;

class RoleMappingSettingsFormTest extends StanfordSamlAuthTestBase {

  public function testForm() {
    $fb = \Drupal::formBuilder();
    $form_state = new FormState();
    $form = $fb->buildForm(RoleMappingSettingsForm::class, $form_state);
    $this->assertNotEmpty($form);

    $form_state->set('mappings', [
      ['role' => 'role1', 'attribute' => 'foo', 'value' => 'bar'],
      ['role' => 'role1', 'attribute' => 'foo', 'value' => 'bar'],
      ['role' => 'role2', 'attribute' => '', 'value' => 'bar'],
      ['role' => 'role3', 'attribute' => 'foo', 'value' => ''],
    ]);
    $form_state->setValue(['role_mapping', 'add'], [
      'role' => 'role4',
      'attribute' => 'bar',
      'value' => 'foo',
    ]);
    $fb->submitForm(RoleMappingSettingsForm::class, $form_state);

    $config = \Drupal::config('stanford_samlauth.settings')->getRawData();
    $this->assertCount(3, $config['role_mapping']['mapping']);
  }

}
