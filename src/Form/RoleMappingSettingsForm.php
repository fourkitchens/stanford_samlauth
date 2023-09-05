<?php

namespace Drupal\stanford_samlauth\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Stanford SAML Authentication settings for this site.
 */
class RoleMappingSettingsForm extends ConfigFormBase {

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, protected EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stanford_samlauth_role_mapping';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['stanford_samlauth.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $settings = $this->config('stanford_samlauth.settings');

    // If the form is newly built, the form state storage will be null. If the
    // form is being rebuilt from an ajax, the storage will be some type of
    // array.
    if (is_null($form_state->get('mappings'))) {
      $form_state->set('mappings', $settings->get('role_mapping.mapping') ?? []);
    }

    $form['reevaluate'] = [
      '#type' => 'radios',
      '#title' => $this->t('Reevaluate roles when the user logs in'),
      '#options' => [
        'new' => $this->t('Grant new roles only. Will only add roles based on role assignments.'),
        'all' => $this->t('Re-evaluate all roles on every log in. This will grant and remove roles.'),
        'none' => $this->t('Do not adjust roles. Allow local administration of roles only.'),
      ],
      '#default_value' => $settings->get('role_mapping.reevaluate') ?? 'new',
    ];

    $form['user_info'] = [
      '#type' => 'container',
      '#states' => [
        'invisible' => ['input[name="reevaluate"]' => ['value' => 'none']],
      ],
    ];

    $form['user_info']['role_mapping'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => $this->getRoleHeaders(),
      '#attributes' => ['id' => 'role-mapping-table'],
    ];

    $form['user_info']['role_mapping']['add']['role'] = [
      '#type' => 'select',
      '#title' => $this->t('Add Role'),
      '#options' => user_role_names(TRUE),
    ];
    unset($form['user_info']['role_mapping']['add']['role']['#options'][RoleInterface::AUTHENTICATED_ID]);
    unset($form['user_info']['role_mapping']['add']['role']['#options'][RoleInterface::ANONYMOUS_ID]);

    $form['user_info']['role_mapping']['add']['attribute'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Attribute Key'),
      '#description' => $this->t('The value in the SAML data to use as the key for matching. eg: eduPersonEnttitlement'),
      '#attributes' => ['placeholder' => 'eduPersonEntitlement'],
    ];

    $form['user_info']['role_mapping']['add']['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Attribute Value'),
      '#description' => $this->t('The value in the SAML data to use as the value for matching. eg: a workgroup like `uit:sws`'),
    ];
    $form['user_info']['role_mapping']['add']['add_mapping'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Mapping'),
      '#submit' => ['::addMappingCallback'],
      '#ajax' => [
        'callback' => '::addMapping',
        'wrapper' => 'role-mapping-table',
      ],
    ];

    foreach ($form_state->get('mappings') as $delta => $role_mapping) {
      $form['user_info']['role_mapping'][$delta] = $this->buildRoleRow($delta, ...$role_mapping);
    }

    $this->buildWorkgroupApiForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $role_mapping = $form_state->get('mappings') ?? [];
    $added_mapping = $form_state->getValue(['role_mapping', 'add']);
    unset($added_mapping['add_mapping']);
    $role_mapping[] = $added_mapping;

    $set_mappings = [];
    foreach ($role_mapping as $mapping) {
      if (!$mapping['attribute']) {
        $mapping['attribute'] = 'eduPersonEntitlement';
      }
      if (!$mapping['value']) {
        continue;
      }
      // Use the md5 to remove any duplicate mapping values.
      $set_mappings[md5(json_encode($mapping))] = $mapping;
    }

    $this->config('stanford_samlauth.settings')
      ->set('role_mapping.workgroup_api.cert', $form_state->getValue('workgroup_api_cert'))
      ->set('role_mapping.workgroup_api.key', $form_state->getValue('workgroup_api_key'))
      ->set('role_mapping.reevaluate', $form_state->getValue('reevaluate'))
      ->set('role_mapping.mapping', array_values($set_mappings))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Add/remove a new workgroup mapping callback.
   *
   * @param array $form
   *   Complete Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   *
   * @return array
   *   Form element.
   */
  public function addMapping(array &$form, FormStateInterface $form_state) {
    return $form['user_info']['role_mapping'];
  }

  /**
   * Add a new workgroup mapping submit callback.
   *
   * @param array $form
   *   Compolete Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   */
  public function addMappingCallback(array $form, FormStateInterface $form_state) {
    $user_input = $form_state->getUserInput();
    $role = $user_input['role_mapping']['add']['role'];
    $value = trim(Html::escape($user_input['role_mapping']['add']['value']));
    $attribute = trim(Html::escape($user_input['role_mapping']['add']['attribute']));
    if ($role && $value) {
      // If the user didn't enter an attribute, use the default one from config.
      $attribute = $attribute ?: 'eduPersonEntitlement';

      $mappings = $form_state->get('mappings');
      $mappings[] = [
        'role' => $role,
        'attribute' => $attribute,
        'value' => $value,
      ];
      $form_state->set('mappings', $mappings);
    }

    $form_state->setRebuild();
  }

  /**
   * Remove a workgroup mapping submit callback.
   *
   * @param array $form
   *   Complete Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   */
  public function removeMappingCallback(array $form, FormStateInterface $form_state) {
    $mappings = $form_state->get('mappings');
    unset($mappings[$form_state->getTriggeringElement()['#mapping']]);
    $form_state->set('mappings', $mappings);
    $form_state->setRebuild();
  }

  /**
   * Get the role mapping table headers.
   *
   * @return array
   *   Array of table header labels.
   */
  protected function getRoleHeaders() {
    return [
      $this->t('Role'),
      $this->t('Attribute'),
      $this->t('Value'),
      $this->t('Actions'),
    ];
  }

  /**
   * Build the table row for the role mapping string.
   *
   * @return array
   *   Table render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function buildRoleRow($delta, $role, $attribute, $value): array {
    $role = $this->entityTypeManager->getStorage('user_role')
      ->load($role);

    return [
      ['#markup' => $role ? $role->label() : $this->t('Broken: @id', ['@id' => $role])],
      ['#markup' => $attribute],
      ['#markup' => $value],
      [
        '#type' => 'submit',
        '#value' => $this->t('Remove Mapping'),
        '#name' => md5(json_encode([
          $role ? $role->id() : $role,
          $attribute,
          $value,
        ])),
        '#mapping' => $delta,
        '#submit' => ['::removeMappingCallback'],
        '#ajax' => [
          'callback' => '::addMapping',
          'wrapper' => 'role-mapping-table',
        ],
      ],
    ];
  }

  /**
   * Build the workgroup api form portion.
   *
   * @param array $form
   *   Complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   */
  protected function buildWorkgroupApiForm(array &$form, FormStateInterface $form_state) {
    $stanford_config = $this->config('stanford_samlauth.settings');
    $form['workgroup_api'] = [
      '#type' => 'container',
      '#states' => [
        'invisible' => ['input[name="reevaluate"]' => ['value' => 'none']],
      ],
    ];
    $form['workgroup_api']['use_workgroup_api'] = [
      '#type' => 'radios',
      '#title' => $this->t('Source to validate role mapping groups against.'),
      '#default_value' => $stanford_config->get('role_mapping.workgroup_api.cert') ? 1 : 0,
      '#options' => [
        $this->t('SAML Attribute'),
        $this->t('Workgroup API'),
      ],
    ];

    $states = [
      'visible' => [
        'input[name="use_workgroup_api"]' => ['value' => 1],
      ],
    ];

    $form['workgroup_api']['workgroup_api_cert'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path to Workgroup API SSL Certificate.'),
      '#description' => $this->t('For more information on how to get a certificate please see: https://uit.stanford.edu/service/registry/certificates.'),
      '#default_value' => $stanford_config->get('role_mapping.workgroup_api.cert'),
      '#states' => $states,
    ];

    $form['workgroup_api']['workgroup_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key to Workgroup API SSL Key.'),
      '#description' => $this->t('For more information on how to get a key please see: https://uit.stanford.edu/service/registry/certificates.'),
      '#default_value' => $stanford_config->get('role_mapping.workgroup_api.key'),
      '#states' => $states,
    ];
  }

}
