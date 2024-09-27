<?php

namespace Drupal\stanford_samlauth\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'Saml Login/Logout Block' block.
 *
 * @Block(
 *  id = "stanford_samlauth_login_block",
 *  admin_label = @Translation("SAML SUNetID Block")
 * )
 */
class SamlLoginBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Current uri the block is displayed on.
   *
   * @var string
   */
  protected $currentUri;

  /**
   * Current user.
   *
   * @var Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('current_user'),
    );
  }

  /**
   * Block constructor.
   *
   * @param array $configuration
   *   Configuration settings.
   * @param string $plugin_id
   *   Block machine name.
   * @param array $plugin_definition
   *   Plugin definition.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Current request stack object.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account interface.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    RequestStack $requestStack,
    AccountInterface $account
    ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUri = $requestStack->getCurrentRequest()?->getPathInfo();
    $this->currentUser = $account;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'link_text' => 'SUNetID Login',
      'logout_link_text' => 'SUNetID Logout',
      'enable_logout_button' => 0,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SUNetID log-in link text'),
      '#description' => $this->t('Here you can replace the text of the SUNetID link.'),
      '#default_value' => $this->configuration['link_text'],
      '#required' => TRUE,
    ];

    $form['enable_logout_button'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable logout button'),
      '#default_value' => $this->configuration['enable_logout_button'],
    ];

    $form['logout_link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SUNetID log-out link text'),
      '#description' => $this->t('Add text to show a link for authenticated users.'),
      '#default_value' => $this->configuration['logout_link_text'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheContexts() {
    $context = parent::getCacheContexts();
    // Make the block cache different for each page and user since the login
    // link has a destination parameter and as based on user login status.
    return Cache::mergeContexts($context, ['url.path', 'user']);
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['link_text'] = $form_state->getValue('link_text');
    $this->configuration['logout_link_text'] = $form_state->getValue('logout_link_text');
    $this->configuration['enable_logout_button'] = $form_state->getValue('enable_logout_button');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $is_anonymous = $this->currentUser->isAnonymous();
    if (!$is_anonymous) {
      if ($this->configuration['enable_logout_button']) {
        $url = Url::fromRoute('user.logout', ['destination' => $this->currentUri]);
        $build['login'] = [
          '#type' => 'html_tag',
          '#tag' => 'a',
          '#value' => $this->configuration['logout_link_text'],
          '#attributes' => [
            'rel' => 'nofollow',
            'href' => $url->toString(),
            'class' => [
              'su-button',
              'decanter-button',
            ],
          ],
        ];
      }
    }
    else {
      $url = Url::fromRoute('samlauth.saml_controller_login', ['destination' => $this->currentUri]);
      $build['login'] = [
        '#type' => 'html_tag',
        '#tag' => 'a',
        '#value' => $this->configuration['link_text'],
        '#attributes' => [
          'rel' => 'nofollow',
          'href' => $url->toString(),
          'class' => [
            'su-button',
            'decanter-button',
          ],
        ],
      ];
    }

    return $build;
  }

}
