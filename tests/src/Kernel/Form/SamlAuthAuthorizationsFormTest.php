<?php

namespace Drupal\Tests\stanford_samlauth\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\stanford_samlauth\Form\SamlAuthAuthorizationsForm;
use Drupal\Tests\stanford_samlauth\Kernel\StanfordSamlAuthTestBase;

class SamlAuthAuthorizationsFormTest extends StanfordSamlAuthTestBase {

  public function testAuthForm(){
    $fb = \Drupal::formBuilder();
    $form_state = new FormState();
    $form = $fb->buildForm(SamlAuthAuthorizationsForm::class, $form_state);
    $this->assertNotEmpty($form['restrict']);

    $form_state->setValues(['restrict' => true, 'users' => 'foo, bar baz', 'groups' => '']);
    $fb->submitForm(SamlAuthAuthorizationsForm::class, $form_state);

    $config = \Drupal::config('stanford_samlauth.settings')->getRawData();
    $this->assertContains('barbaz', $config['allowed']['users']);
  }

}
