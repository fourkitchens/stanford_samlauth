<?php

namespace Drupal\stanford_samlauth\EventSubscriber;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\samlauth\Event\SamlauthEvents;
use Drupal\samlauth\Event\SamlauthUserSyncEvent;
use Drupal\samlauth\UserVisibleException;
use Drupal\stanford_samlauth\Service\WorkgroupApiInterface;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Stanford SAML Authentication event subscriber.
 */
class StanfordSamlAuthSubscriber implements EventSubscriberInterface {

  /**
   * A config object with saml settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $samlConfig;

  /**
   * A config object with stanford_samlauth settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $stanfordConfig;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      SamlauthEvents::USER_SYNC => 'onSamlUserSync',
      KernelEvents::REQUEST => 'onKernelRequest',
    ];
  }

  /**
   * StanfordSSPEventSubscriber constructor.
   *
   * @param \Drupal\stanford_samlauth\Service\WorkgroupApiInterface $workgroupApi
   *   Samlauth workgroup api service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \Drupal\Core\Session\AccountProxyInterface $userAccount
   *   Current user object.
   * @param \Drupal\Core\Path\PathMatcherInterface $pathMatcher
   *   Path matcher service.
   * @param \Drupal\Core\Path\CurrentPathStack $currentPath
   *   Current path service.
   * @param \Drupal\path_alias\AliasManagerInterface $aliasManager
   *   Alias manager service.
   */
  public function __construct(protected WorkgroupApiInterface $workgroupApi, ConfigFactoryInterface $config_factory, protected AccountProxyInterface $userAccount, protected PathMatcherInterface $pathMatcher, protected CurrentPathStack $currentPath, protected AliasManagerInterface $aliasManager) {
    $this->samlConfig = $config_factory->get('samlauth.settings');
    $this->stanfordConfig = $config_factory->get('stanford_samlauth.settings');
  }

  /**
   * Check for authorization and do role mapping when a user is logging in.
   *
   * @param \Drupal\samlauth\Event\SamlauthUserSyncEvent $event
   *   Saml auth event after external auth event.
   */
  public function onSamlUserSync(SamlauthUserSyncEvent $event) {
    $account = $event->getAccount();
    $account->set('affiliation', $event->getAttributes()['eduPersonAffiliation'] ?? []);

    // Make sure the user is authorized first.
    if (!$this->userIsAllowed($account, $event->getAttributes())) {
      throw new UserVisibleException('Unauthorized login attempt');
    }

    // Do the role mapping.
    if ($this->assignRoleMapping($account, $event->getAttributes())) {
      $event->markAccountChanged();
    }
  }

  /**
   * Assign appropriate roles based on configured role mapping values.
   *
   * @param \Drupal\user\UserInterface $account
   *   User account.
   * @param array $attributes
   *   SAML attributes.
   *
   * @return bool
   *   If the account was modified at all.
   */
  protected function assignRoleMapping(UserInterface $account, array $attributes): bool {
    $changed_account = FALSE;
    $evaluate = $this->stanfordConfig->get('role_mapping.reevaluate');
    // Don't do any role mapping.
    if ($evaluate == 'none') {
      return FALSE;
    }

    if ($evaluate == 'all') {
      // If configured to re-evaluate all roles, simply first remove them all.
      // We will add them back in the later steps.
      foreach ($account->getRoles() as $role) {
        $account->removeRole($role);
        $changed_account = TRUE;
      }
    }

    // Add affiliation roles first.
    $affiliations = [
      'staff' => 'stanford_staff',
      'faculty' => 'stanford_faculty',
      'student' => 'stanford_student',
    ];
    $user_affiliations = $attributes['eduPersonAffiliation'] ?? [];
    foreach (array_intersect($user_affiliations, array_keys($affiliations)) as $key) {
      $account->addRole($affiliations[$key]);
      $changed_account = TRUE;
    }

    // When configured to use the workgroup API, fetch all the user's workgroups
    // and compare them to the role mapping settings.
    if ($this->stanfordConfig->get('role_mapping.workgroup_api.cert')) {
      $workgroups = $this->workgroupApi->getAllUserWorkgroups($account->getAccountName());
      foreach ($this->stanfordConfig->get('role_mapping.mapping') as $role_mapping) {
        if (in_array($role_mapping['value'], $workgroups)) {
          $account->addRole($role_mapping['role']);
          $changed_account = TRUE;
        }
      }
      return $changed_account;
    }

    // Compare the saml attribute values to the accepted values.
    foreach ($this->stanfordConfig->get('role_mapping.mapping') as $role_mapping) {
      $saml_value = NestedArray::getValue($attributes, explode('|', $role_mapping['attribute']));
      // Either the value matches exactly, or the expected value is in the saml
      // data attribute array.
      if (
        $saml_value == $role_mapping['value'] ||
        (is_array($saml_value) && in_array($role_mapping['value'], $saml_value))
      ) {
        $account->addRole($role_mapping['role']);
        $changed_account = TRUE;
      }
    }

    return $changed_account;
  }

  /**
   * Check if the user is allowed to log into the site.
   *
   * @param \Drupal\user\UserInterface $account
   *   Saml User account.
   * @param array $attributes
   *   Saml attributes.
   *
   * @return bool
   *   If the user is allowed.
   */
  protected function userIsAllowed(UserInterface $account, array $attributes): bool {
    // All users are allowed.
    if (!$this->stanfordConfig->get('allowed.restrict')) {
      return TRUE;
    }

    // Simple sunetID check.
    if (in_array($account->getAccountName(), $this->stanfordConfig->get('allowed.users'))) {
      return TRUE;
    }

    // Staff, Faculty, Student, Member check.
    $allowed_affiliations = $this->stanfordConfig->get('allowed.affiliations');
    /** @var \Drupal\Core\Field\FieldItemInterface $affiliation */
    foreach ($account->get('affiliation') as $affiliation) {
      if (in_array($affiliation->getString(), $allowed_affiliations)) {
        return TRUE;
      }
    }

    $allowed_groups = $this->stanfordConfig->get('allowed.groups') ?: [];

    // The `eduPersonEntitlement` contains the workgroup data, but it isn't,
    // always available. It depends on the individual SP and the workgroup
    // release policy.
    $entitlements = $attributes['eduPersonEntitlement'] ?? [];

    if (array_intersect($allowed_groups, $entitlements)) {
      return TRUE;
    }

    // Use the workgroup API to check if the user exists in any of the allowed
    // workgroups.
    return $this->workgroupApi->userInAnyGroup($allowed_groups, $account->getAccountName());
  }

  /**
   * Redirect user create page if local login is disabled.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   Triggered kernel event.
   */
  public function onKernelRequest(RequestEvent $event) {
    $request = $event->getRequest();
    try {
      $route = $request->attributes->get('_route');
      if (!$this->stanfordConfig->get('hide_local_login')) {
        return;
      }
      if ($route == 'user.admin_create') {
        $destination = Url::fromRoute('stanford_samlauth.create_user')
          ->toString();
        $event->setResponse(new RedirectResponse($destination));
        return;
      }
    }
    catch (\Throwable $e) {
      // Safety catch to avoid errors.
    }
  }

}
