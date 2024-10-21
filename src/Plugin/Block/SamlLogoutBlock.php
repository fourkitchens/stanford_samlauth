<?php

namespace Drupal\stanford_samlauth\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'Saml Logout Block' block.
 *
 * @Block(
 *  id = "stanford_samlauth_logout_block",
 *  admin_label = @Translation("SAML SUNetID Logout Block")
 * )
 */
class SamlLogoutBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Current uri the block is displayed on.
   *
   * @var string
   */
  protected $currentUri;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack')
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
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, RequestStack $requestStack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUri = $requestStack->getCurrentRequest() ?->getPathInfo();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['link_text' => 'SUNetID Logout'] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SUNetID log-out link text'),
      '#description' => $this->t('Add text to show a link for authenticated users.'),
      '#default_value' => $this->configuration['link_text'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $context = parent::getCacheContexts();
    // Make the block cache different for each page since the login link has a
    // destination parameter.
    return Cache::mergeContexts($context, ['url.path']);
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account, $return_as_object = FALSE) {
    $access = AccessResult::allowedIf($account->isAuthenticated());
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['link_text'] = $form_state->getValue('link_text');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $url = Url::fromRoute('user.logout', ['destination' => $this->currentUri]);
    $build = [];
    $build['logout'] = [
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
    return $build;
  }

}
