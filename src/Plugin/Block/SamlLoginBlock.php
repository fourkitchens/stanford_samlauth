<?php

namespace Drupal\stanford_samlauth\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RedirectDestination;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Saml Login Block' block.
 *
 * @Block(
 *  id = "stanford_samlauth_login_block",
 *  admin_label = @Translation("SAML SUNetID Login Block")
 * )
 */
class SamlLoginBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * RedirectDestination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestination
   */
  private RedirectDestination $redirectDestination;

  /**
   * Internal path to the front page.
   *
   * @var string
   */
  private mixed $frontPage;

  /**
   * PathAliasManager service.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  private AliasManagerInterface $pathAliasManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('redirect.destination'),
      $container->get('config.factory'),
      $container->get('path_alias.manager')
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
   * @param \Drupal\Core\Routing\RedirectDestination $redirectDestination
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\path_alias\AliasManagerInterface $pathAliasManager
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, RedirectDestination $redirectDestination, ConfigFactoryInterface $configFactory, AliasManagerInterface $pathAliasManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->redirectDestination = $redirectDestination;
    $this->frontPage = $configFactory->get('system.site')->get('page.front');
    $this->pathAliasManager = $pathAliasManager;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['link_text' => 'SUNetID Login'] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text of the SUNetID link'),
      '#description' => $this->t('Here you can replace the text of the SUNetID link.'),
      '#default_value' => $this->configuration['link_text'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
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
    $access = AccessResult::allowedIf($account->isAnonymous());
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['link_text'] = $form_state->getValue('link_text');
  }

  /**
   * Get the destination of the current request.
   *
   * * Returns any destination parameter if present in the current URL.
   *   E.g. `stanford.edu?destination=/foo/bar`.
   * * Otherwise the current path.
   * * Except if that's the homepage, in which case an empty array is returned.
   *
   * This ensures that someone clicking the log-in button on the homepage does
   * not get redirected back to the homepage post-login and instead sees the
   * standard post-login page (dashboard, etc.).
   *
   * @return array
   */
  private function getDestination(): array {
    $front_alias = $this->pathAliasManager->getAliasByPath($this->frontPage);
    if ($this->redirectDestination->get() === '/' || $this->redirectDestination->get() === $front_alias) {
      return [];
    }
    return $this->redirectDestination->getAsArray();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $url = Url::fromRoute('samlauth.saml_controller_login', $this->getDestination());
    $build = [];
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
    return $build;
  }

}
