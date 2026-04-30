<?php

namespace Drupal\stanford_samlauth\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\Translatablemarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Saml Login Block' block.
 */
#[Block(
  id: 'stanford_samlauth_login_block',
  admin_label: new TranslatableMarkup('SAML SUNetID Login Block')
)]
class SamlLoginBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * RedirectDestination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  private RedirectDestinationInterface $redirectDestination;

  /**
   * PathMatcher service.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  private PathMatcherInterface $pathMatcher;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('redirect.destination'),
      $container->get('path.matcher')
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
   *   The redirect destination service.
   * @param \Drupal\Core\Path\PathMatcherInterface $pathMatcher
   *   The path matcher service.
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, RedirectDestinationInterface $redirectDestination, PathMatcherInterface $pathMatcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->redirectDestination = $redirectDestination;
    $this->pathMatcher = $pathMatcher;
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
    return Cache::mergeContexts($context, ['url']);
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
   * When the block is rendered on the front page, returns an empty array so
   * that no `destination` parameter is appended to the login URL. This ensures
   * that someone clicking the log-in button on the homepage does not get
   * redirected back to the homepage post-login and instead sees the site's
   * standard post-login page (dashboard, etc.).
   *
   * Otherwise, returns the current redirect destination as an array so that
   * the user is returned to the page they logged in from.
   *
   * @return array
   *   Either an empty array or a `['destination' => ...]` array suitable for
   *   passing as route parameters to the login URL.
   */
  protected function getDestination(): array {
    if ($this->pathMatcher->isFrontPage()) {
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
