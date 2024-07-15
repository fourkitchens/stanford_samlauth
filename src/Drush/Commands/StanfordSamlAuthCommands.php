<?php

namespace Drupal\stanford_samlauth\Drush\Commands;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\externalauth\AuthmapInterface;
use Drupal\stanford_samlauth\Form\SamlAuthCreateUserForm;
use Drupal\user\RoleInterface;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Drush\Attributes as CLI;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Samlauth drush comamnds.
 */
#[CLI\Bootstrap(DrupalBootLevels::FULL)]
class StanfordSamlAuthCommands extends DrushCommands {

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
   * Instantiates a new instance of the implementing class using autowiring.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container this instance should use.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('externalauth.authmap'),
      $container->get('form_builder'),
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Drush command constructor.
   *
   * @param \Drupal\externalauth\AuthmapInterface $authMap
   *   Authmap service.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   Form builder service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   */
  public function __construct(protected readonly AuthmapInterface $authMap, protected readonly FormBuilderInterface $formBuilder, ConfigFactoryInterface $config_factory, protected readonly EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct();
    $this->samlConfig = $config_factory->getEditable('samlauth.authentication');
    $this->stanfordConfig = $config_factory->getEditable('stanford_samlauth.settings');
  }

  /**
   * Map a SAML entitlement to a role.
   */
  #[CLI\Command(name: 'saml:entitlement-role', aliases: [
    'saml-ser',
    'saml-entitlement-role',
  ])]
  #[CLI\Argument(name: 'entitlement', description: 'A value from eduPersonEntitlement Saml Assertion.')]
  #[CLI\Argument(name: 'role_id', description: 'Role machine name.')]
  #[CLI\Usage(name: 'saml:entitlement-role anchorage_support site_editor', description: 'Map the users with "anchorage_support" entitlement to the site_editor role.')]
  public function entitlementRole(string $entitlement, string $role_id) {
    $role_id = Html::escape($role_id);
    $role = $this->entityTypeManager->getStorage('user_role')->load($role_id);
    // Validate the role exists.
    if (!$role) {
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
   */
  #[CLI\Command(name: 'saml:add-user', aliases: ['saml-au', 'saml-add-user'])]
  #[CLI\Argument(name: 'sunetid', description: 'A sunet id.')]
  #[CLI\Option(name: 'name', description: 'The user\'s name.')]
  #[CLI\Option(name: 'email', description: 'The user\'s email.')]
  #[CLI\Option(name: 'roles', description: 'Comma separated list of role names.')]
  #[CLI\Option(name: 'send-email', description: 'Send email to the user?')]
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
    $existing_roles = $this->entityTypeManager->getStorage('user_role')
      ->loadMultiple();
    unset($existing_roles[RoleInterface::AUTHENTICATED_ID], $existing_roles[RoleInterface::ANONYMOUS_ID]);

    $options['roles'] = array_intersect(array_keys($existing_roles), $options['roles']);
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
