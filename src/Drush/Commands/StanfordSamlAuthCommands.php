<?php

namespace Drupal\stanford_samlauth\Drush\Commands;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\externalauth\AuthmapInterface;
use Drupal\stanford_samlauth\Form\SamlAuthCreateUserForm;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 */
class StanfordSamlAuthCommands extends DrushCommands {

  /**
   * External authmap service.
   *
   * @var \Drupal\externalauth\AuthmapInterface
   */
  protected $authmap;

  /**
   * Form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Config object of SAML settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $samlConfig;

  /**
   * A config object with stanford_ssp settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $stanfordConfig;

  /**
   * StanfordSspCommands constructor.
   *
   * @param \Drupal\externalauth\AuthmapInterface $auth_map
   *   Authmap service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   Form builder service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   */
  public function __construct(AuthmapInterface $auth_map, FormBuilderInterface $form_builder, ConfigFactoryInterface $config_factory) {
    $this->authmap = $auth_map;
    $this->formBuilder = $form_builder;
    $this->samlConfig = $config_factory->getEditable('samlauth.authentication');
    $this->stanfordConfig = $config_factory->getEditable('stanford_samlauth.settings');
  }

  /**
   * Map a SAML entitlement to a role.
   *
   * @param string $entitlement
   *   A value from eduPersonEntitlement, e.g., "anchorage_support".
   * @param string $role_id
   *   The name of the role, e.g., "stanford_staff".
   *
   * @command saml:entitlement-role
   * @aliases saml-ser,saml-entitlement-role
   */
  public function entitlementRole(string $entitlement, string $role_id) {
    $role_id = Html::escape($role_id);
    $existing_roles = user_roles(TRUE);

    // Validate the role exists.
    if (!isset($existing_roles[$role_id])) {
      $this->logger->error(dt('No role exists with the ID "%role_id".', ['%role_id' => $role_id]));
      return;
    }

    // Add the mapping to the existing config.
    $role_mappings = $this->stanfordConfig->get('role_mapping.mapping') ?: [];
    $role_mappings[] = [
      'role' => $role_id,
      'attribute' => 'eduPersonEntitlement',
      'value' => $entitlement,
    ];
    $this->stanfordConfig->set('role_mapping.mapping', $role_mappings)->save();

    $message = dt('Mapped the "@entitlement" entitlement to the "@role" role.', [
      '@entitlement' => $entitlement,
      '@role' => $role_id,
    ]);
    $this->output->writeln($message);
    $this->logger->info($message);
  }

  /**
   * Add a SSO enabled user.
   *
   * @param string $sunetid
   *   A sunet id.
   * @param array $options
   *   An associative array of options.
   *
   * @option name
   *   The user's name.
   * @option email
   *   The user's email.
   * @option roles
   *   Comma separated list of role names.
   * @option send-email
   *   Send email to the user?
   *
   * @command saml:add-user
   * @aliases saml-au,saml-add-user
   */
  public function addUser(string $sunetid, array $options = [
    'name' => NULL,
    'email' => NULL,
    'roles' => NULL,
    'send-email' => NULL,
  ]
  ) {
    foreach ($options as &$value) {
      if (is_string($value)) {
        $value = Html::escape($value);
      }
    }

    // Build the roles array and make sure to only add the ones that exist.
    $options['roles'] = array_filter(explode(',', $options['roles'] ?: ''));
    $existing_roles = array_keys(user_roles(TRUE));
    $options['roles'] = array_intersect($existing_roles, $options['roles']);
    $options['sunetid'] = $sunetid;

    $options = array_filter($options);
    if (!isset($options['roles'])) {
      $options['roles'] = [];
    }

    // Use the form to provide validation and such.
    $form_state = new FormState();
    $form_state->setValues($options);
    $this->formBuilder->submitForm(SamlAuthCreateUserForm::class, $form_state);

    if ($form_state::hasAnyErrors()) {
      $errors = $form_state->getErrors();
      throw new \Exception((string) reset($errors));
    }
  }

}
