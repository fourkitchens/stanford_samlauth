<?php

declare(strict_types=1);

namespace Drupal\stanford_samlauth\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Samlauth Hooks.
 */
class StanfordSamlAuthHooks {

  /**
   * Hook constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory service.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager, protected ConfigFactoryInterface $configFactory) {}

  /**
   * Add affiliation field to user entity.
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type) {
    $fields = [];
    if ($entity_type->id() == 'user') {
      $fields['affiliation'] = BaseFieldDefinition::create('string')
        ->setLabel(t('Affiliation'))
        ->setDescription(t("User's affiliation as defined by SAML data."))
        ->setStorageRequired(TRUE)
        ->setCardinality(-1);
    }
    return $fields;
  }

  /**
   * Modifies the local tasks for the user login and registration pages.
   */
  #[Hook('menu_local_tasks_alter')]
  function menuLocalTasksAlter(&$data, $route_name, RefinableCacheableDependencyInterface &$cacheability) {
    if ($route_name == 'user.login' || $route_name == 'user.register') {
      $config = $this->configFactory->get('stanford_samlauth.settings');
      if ($config->get('hide_local_login')) {
        // Hide local tabs that have the "Recover Password" tab if local login
        // isn't allowed.
        unset($data['tabs']);
      }
    }
  }

  /**
   * Alters the user login form to add a SAML login link and modify the
   * local login form.
   */
  #[Hook('form_user_login_form_alter')]
  function userLoginFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    $link_text = $this->configFactory->get('samlauth.authentication')
      ->get('login_menu_item_title');
    $form['#attached']['library'][] = 'stanford_samlauth/samlauth';
    $form['saml'] = [
      '#type' => 'html_tag',
      '#weight' => -99,
      '#tag' => 'a',
      '#value' => $link_text ?: t('Stanford Login'),
      '#attributes' => [
        'rel' => 'nofollow',
        'href' => '/saml/login',
        'class' => [
          'samlauth-login',
          'su-button',
          'decanter-button',
        ],
      ],
    ];

    $config = $this->configFactory->get('stanford_samlauth.settings');

    $form['login_title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h1',
      '#value' => t('Login'),
      '#weight' => -999,
    ];
    $form['intro_text'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => t('Welcome back! Log in to access your website'),
      '#weight' => -998,
    ];

    // If configured to disallow local login, hide the local login form parts.
    if ($config->get('hide_local_login')) {
      unset($form['name'], $form['pass'], $form['actions']);
      return;
    }

    // Moves the original form elements into a collapsed group.
    $form['manual'] = [
      '#type' => 'details',
      '#title' => $config->get('local_login_fieldset_label') ?: t('Drupal Login'),
      '#open' => $config->get('local_login_fieldset_open') ?: FALSE,
    ];
    $form['manual']['name'] = $form['name'];
    $form['manual']['pass'] = $form['pass'];
    $form['manual']['actions'] = $form['actions'];
    $form['manual']['actions']['reset'] = [
      '#type' => 'link',
      '#url' => Url::fromRoute('user.pass'),
      '#title' => t('Reset Password'),
    ];
    unset($form['name'], $form['pass'], $form['actions']);
  }

}
