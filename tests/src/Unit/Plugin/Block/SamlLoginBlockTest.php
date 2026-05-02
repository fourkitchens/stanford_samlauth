<?php

namespace Drupal\Tests\stanford_samlauth\Unit\Plugin\Block;

use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\stanford_samlauth\Plugin\Block\SamlLoginBlock;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class SamlLoginBlockTest
 */
class SamlLoginBlockTest extends UnitTestCase {

  /**
   * The block plugin.
   *
   * @var \Drupal\stanford_samlauth\Plugin\Block\SamlLoginBlock
   */
  protected $block;

  /**
   * The path matcher mock.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * The URL generator mock.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * The redirect destination mock.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * {@inheritDoc}
   */
  public function setup(): void {
    parent::setUp();

    $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $this->urlGenerator->method('generateFromRoute')->willReturn('/foo-bar');

    $request_stack = new RequestStack();

    $context_manager = $this->createMock(CacheContextsManager::class);
    $context_manager->method('assertValidTokens')->willReturn(TRUE);

    $this->redirectDestination = $this->createMock(RedirectDestinationInterface::class);
    $this->redirectDestination->method('getAsArray')->willReturn(['destination' => '/some/path']);

    $this->pathMatcher = $this->createMock(PathMatcherInterface::class);

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->set('url_generator', $this->urlGenerator);
    $container->set('request_stack', $request_stack);
    $container->set('cache_contexts_manager', $context_manager);
    $container->set('redirect.destination', $this->redirectDestination);
    $container->set('path.matcher', $this->pathMatcher);
    \Drupal::setContainer($container);

    $this->block = SamlLoginBlock::create($container, [], 'saml_login', ['provider' => 'stanford_samlauth']);
  }

  /**
   * Test configuration and form methods.
   */
  public function testBlock() {
    $this->assertEquals(['link_text' => 'SUNetID Login'], $this->block->defaultConfiguration());
    $form_state = new FormState();
    $form = $this->block->blockForm([], $form_state);
    $this->assertCount(1, $form);
    $this->assertArrayHasKey('link_text', $form);

    $link_text = $this->getRandomGenerator()->string();
    $form_state->setValue('link_text', $link_text);
    $this->block->blockSubmit($form, $form_state);
    $new_config = $this->block->getConfiguration();
    $this->assertEquals($link_text, $new_config['link_text']);
  }

  /**
   * Test anonymous users would access the block, authenticated would not.
   */
  public function testAccess() {
    $this->assertContains('url', $this->block->getCacheContexts());

    $account = $this->createMock(AccountInterface::class);
    $account->method('isAnonymous')->willReturn(TRUE);
    $this->assertTrue($this->block->access($account));

    $account = $this->createMock(AccountInterface::class);
    $account->method('isAnonymous')->willReturn(FALSE);
    $this->assertFALSE($this->block->access($account));
  }

  /**
   * Test build render array is structured correctly.
   */
  public function testBuild() {
    $this->pathMatcher->method('isFrontPage')->willReturn(FALSE);
    $this->urlGenerator->expects($this->once())
      ->method('generateFromRoute')
      ->with('samlauth.saml_controller_login', ['destination' => '/some/path'])
      ->willReturn('/foo-bar');
    $build = $this->block->build();
    $this->assertCount(1, $build);
    $this->assertArrayHasKey('login', $build);
    $this->assertEquals( 'html_tag', $build['login']['#type']);
    $this->assertEquals('/foo-bar', $build['login']['#attributes']['href']);
  }

  /**
   * Test that no destination is forwarded when on the front page.
   */
  public function testBuildOnFrontPage() {
    $this->pathMatcher->method('isFrontPage')->willReturn(TRUE);
    $this->urlGenerator->expects($this->once())
      ->method('generateFromRoute')
      ->with('samlauth.saml_controller_login', [])
      ->willReturn('/foo-bar');
    $build = $this->block->build();
    $this->assertEquals('/foo-bar', $build['login']['#attributes']['href']);
  }

}
