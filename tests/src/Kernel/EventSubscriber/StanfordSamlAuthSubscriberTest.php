<?php

namespace Drupal\Tests\stanford_samlauth\Kernel\EventSubscriber;

use Drupal\samlauth\Event\SamlauthEvents;
use Drupal\samlauth\Event\SamlauthUserSyncEvent;
use Drupal\samlauth\UserVisibleException;
use Drupal\Tests\stanford_samlauth\Kernel\StanfordSamlAuthTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class StanfordSamlAuthSubscriberTest extends StanfordSamlAuthTestBase {

  use UserCreationTrait;

  public function testUserSyncEvent() {
    $role_mapping = [
      [
        'role' => 'role1',
        'attribute' => 'fakeAttribute',
        'value' => 'foobar',
      ],
    ];
    \Drupal::configFactory()
      ->getEditable('stanford_samlauth.settings')
      ->set('role_mapping.mapping', $role_mapping)
      ->save();

    $account = User::create(['name' => 'bob', 'mail' => 'bob@example.com']);
    $attributes = [
      'uid' => ['bob'],
      'eduPersonAffiliation' => ['staff', 'member'],
      'fakeAttribute' => 'foobar',
    ];

    $event = new SamlauthUserSyncEvent($account, $attributes, FALSE);
    \Drupal::service('event_dispatcher')
      ->dispatch($event, SamlauthEvents::USER_SYNC);

    $this->assertNotEmpty($account->get('affiliation'));
    $this->assertContains('role1', $account->getRoles());
  }

  public function testInvalidSunet() {
    $restrictions = [
      'restrict' => TRUE,
      'users' => ['foobar'],
      'affiliations' => [],
      'groups' => [],
    ];

    \Drupal::configFactory()
      ->getEditable('stanford_samlauth.settings')
      ->set('allowed', $restrictions)
      ->save();

    $attributes = [
      'uid' => ['foobar'],
      'eduPersonAffiliation' => ['staff', 'member'],
    ];
    $account = User::create([
      'name' => 'foobar',
      'mail' => 'foobar@example.com',
      'status' => 1,
    ]);
    $event = new SamlauthUserSyncEvent($account, $attributes, FALSE);
    \Drupal::service('event_dispatcher')
      ->dispatch($event, SamlauthEvents::USER_SYNC);
    $this->assertFalse($account->isBlocked());

    $attributes['uid'] = ['bob'];
    $account = User::create([
      'name' => 'bob',
      'mail' => 'bob@example.com',
      'status' => 1,
    ]);
    $event = new SamlauthUserSyncEvent($account, $attributes, FALSE);
    $this->expectException(UserVisibleException::class);
    \Drupal::service('event_dispatcher')
      ->dispatch($event, SamlauthEvents::USER_SYNC);
  }

  public function testInvalidAffiliation() {
    $restrictions = [
      'restrict' => TRUE,
      'users' => [],
      'affiliations' => ['faculty'],
      'groups' => [],
    ];

    \Drupal::configFactory()
      ->getEditable('stanford_samlauth.settings')
      ->set('allowed', $restrictions)
      ->save();

    $account = User::create([
      'name' => 'foobar',
      'mail' => 'foobar@example.com',
      'status' => 1,
    ]);
    $attributes = [
      'uid' => ['foobar'],
      'eduPersonAffiliation' => ['staff', 'faculty'],
    ];
    $event = new SamlauthUserSyncEvent($account, $attributes, FALSE);
    \Drupal::service('event_dispatcher')
      ->dispatch($event, SamlauthEvents::USER_SYNC);
    $this->assertFalse($account->isBlocked());

    $account = User::create([
      'name' => 'bob',
      'mail' => 'bob@example.com',
      'status' => 1,
    ]);
    $attributes = [
      'uid' => ['bob'],
      'eduPersonAffiliation' => ['staff', 'member'],
    ];
    $event = new SamlauthUserSyncEvent($account, $attributes, FALSE);
    $this->expectException(UserVisibleException::class);
    \Drupal::service('event_dispatcher')
      ->dispatch($event, SamlauthEvents::USER_SYNC);
  }

  public function testInvalidGroup() {
    $restrictions = [
      'restrict' => TRUE,
      'users' => [],
      'affiliations' => [],
      'groups' => ['foobar'],
    ];

    \Drupal::configFactory()
      ->getEditable('stanford_samlauth.settings')
      ->set('allowed', $restrictions)
      ->save(TRUE);

    $account = User::create([
      'name' => 'foobar',
      'mail' => 'foobar@example.com',
      'status' => 1,
    ]);
    $attributes = ['uid' => ['foobar'], 'eduPersonEntitlement' => ['foobar']];
    $event = new SamlauthUserSyncEvent($account, $attributes, FALSE);
    $this->expectException(UserVisibleException::class);
    \Drupal::service('event_dispatcher')
      ->dispatch($event, SamlauthEvents::USER_SYNC);
    $this->assertFalse($account->isBlocked());

    $account = User::create([
      'name' => 'bob',
      'mail' => 'bob@example.com',
      'status' => 1,
    ]);
    $attributes = ['uid' => ['bob'], 'eduPersonEntitlement' => ['member']];
    $event = new SamlauthUserSyncEvent($account, $attributes, FALSE);
    $this->expectException(UserVisibleException::class);
    \Drupal::service('event_dispatcher')
      ->dispatch($event, SamlauthEvents::USER_SYNC);
  }

  public function testKernelRequest() {
    $user = $this->createUser([]);
    $user->addRole('administrator');
    $user->save();
    $this->setCurrentUser($user);

    $kernel = $this->container->get('kernel');
    $request = Request::create('/admin/people/create');
    $event = new RequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);
    \Drupal::service('event_dispatcher')
      ->dispatch($event, KernelEvents::REQUEST);

    $this->assertInstanceOf(RedirectResponse::class, $event->getResponse());
  }

}
