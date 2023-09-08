<?php

namespace Drupal\Tests\stanford_samlauth\Unit\Plugin\Block;

use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\stanford_samlauth\Plugin\Block\SamlLoginBlock;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class SamlLoginBlockTest
 *
 * @package Drupal\Tests\stanford_samlauth\Unit\Plugin\Block
 * @covers \Drupal\stanford_samlauth\Plugin\Block\SamlLoginBlock
 */
class SamlLoginBlockTest extends UnitTestCase {

  /**
   * The block plugin.
   *
   * @var \Drupal\stanford_samlauth\Plugin\Block\SamlLoginBlock
   */
  protected $block;

  /**
   * {@inheritDoc}
   */
  public function setup(): void {
    parent::setUp();

    $url_generator = $this->createMock(UrlGeneratorInterface::class);
    $url_generator->method('generateFromRoute')->willReturn('/foo-bar');

    $request_stack = new RequestStack();

    $context_manager = $this->createMock(CacheContextsManager::class);
    $context_manager->method('assertValidTokens')->willReturn(TRUE);

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->set('url_generator', $url_generator);
    $container->set('request_stack', $request_stack);
    $container->set('cache_contexts_manager', $context_manager);
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
    $this->assertContains('url.path', $this->block->getCacheContexts());

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
    $build = $this->block->build();
    $this->assertCount(1, $build);
    $this->assertArrayHasKey('login', $build);
    $this->assertEquals( 'html_tag', $build['login']['#type']);
    $this->assertEquals('/foo-bar', $build['login']['#attributes']['href']);
  }

}
