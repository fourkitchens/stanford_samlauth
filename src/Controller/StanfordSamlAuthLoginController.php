<?php

namespace Drupal\stanford_samlauth\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Stanford SAML Authentication routes.
 */
class StanfordSamlAuthLoginController extends ControllerBase {

  /**
   * Redirect legacy routes to the samlauth route with a homepage destination.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   *
   * @codeCoverageIgnore Nothing to test here.
   */
  public function login() {
    return $this->redirect('samlauth.saml_controller_login', [], ['query' => ['destination' => '/']]);
  }

}
