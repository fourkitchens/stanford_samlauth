<?php

namespace Drupal\stanford_samlauth\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Workgroup api service class to connect to the API.
 *
 * @package Drupal\stanford_samlauth\Service
 *
 * @phpstan-type WorkgroupInfo array{
 *   lastUpdated: string,
 *   description: string,
 *   lastUpdatedBy: string,
 *   name: string,
 *   memberCount: string,
 * }
 * @phpstan-type WorkgroupAPIResponse array{
 *   id: string,
 *   type: string,
 *   result: array<string, WorkgroupInfo>
 * }
 */
class WorkgroupApi implements WorkgroupApiInterface {

  const WORKGROUP_API = 'https://workgroupsvc.stanford.edu/workgroups/2.0';

  /**
   * Logger channel service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Path to cert file.
   *
   * @var string
   */
  protected $cert;

  /**
   * Path to key file.
   *
   * @var string
   */
  protected $key;

  /**
   * Keyed array of api responses, keyed by the type and the id of the request.
   *
   * @var array{
   *   workgroup?: array<string, WorkgroupAPIResponse>,
   *   user?: array<string, WorkgroupAPIResponse>
   * }
   */
  protected $responses = [];

  /**
   * StanfordSSPWorkgroupApi constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory service.
   * @param \GuzzleHttp\ClientInterface $guzzle
   *   Http client guzzle service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   Logger channel factory service.
   */
  public function __construct(protected ConfigFactoryInterface $configFactory, protected ClientInterface $guzzle, LoggerChannelFactoryInterface $logger) {
    $this->logger = $logger->get('stanford_samlauth');

    $config = $this->configFactory->get('stanford_samlauth.settings');
    $cert_path = $config->get('role_mapping.workgroup_api.cert');
    $key_path = $config->get('role_mapping.workgroup_api.key');

    if ($cert_path && is_file($cert_path) && $key_path && is_file($key_path)) {
      $this->setCert($cert_path);
      $this->setKey($key_path);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setCert(string $cert_path): void {
    $this->cert = $cert_path;
  }

  /**
   * {@inheritdoc}
   */
  public function setKey(string $key_path): void {
    $this->key = $key_path;
  }

  /**
   * {@inheritdoc}
   */
  public function getCert(): ?string {
    return $this->cert;
  }

  /**
   * {@inheritdoc}
   */
  public function getKey(): ?string {
    return $this->key;
  }

  /**
   * {@inheritdoc}
   */
  public function connectionSuccessful(): bool {
    return !empty($this->callApi('uit:sws'));
  }

  /**
   * {@inheritdoc}
   */
  public function userInGroup(string $workgroup, string $name): bool {
    return in_array($workgroup, $this->getAllUserWorkgroups($name));
  }

  /**
   * {@inheritdoc}
   */
  public function userInAnyGroup(array $workgroups, string $name): bool {
    return !empty(array_intersect($workgroups, $this->getAllUserWorkgroups($name)));
  }

  /**
   * {@inheritdoc}
   */
  public function userInAllGroups(array $workgroups, string $name): bool {
    return count(array_intersect($workgroups, $this->getAllUserWorkgroups($name))) == count($workgroups);
  }

  /**
   * {@inheritDoc}
   */
  public function isWorkgroupValid(string $workgroup): bool {
    return !empty($this->callApi($workgroup));
  }

  /**
   * {@inheritDoc}
   */
  public function isSunetValid(string $sunet): bool {
    return !!$this->callApi(NULL, $sunet);
  }

  /**
   * Call the workgroup api and get the response for the workgroup.
   *
   * @param string|null $workgroup
   *   Workgroup name like uit:sws.
   * @param string|null $sunet
   *   User sunetid.
   *
   * @return null|array<WorkgroupAPIResponse>
   *   API response or false if fails.
   */
  protected function callApi(string $workgroup = NULL, string $sunet = NULL): ?array {
    $type = $workgroup ? 'workgroup' : 'user';
    $id = $workgroup ?: $sunet;
    if (isset($this->responses[$type][$id])) {
      return $this->responses[$type][$id];
    }

    $config = $this->configFactory->get('stanford_samlauth.settings');
    $options = [
      'cert' => $this->getCert(),
      'ssl_key' => $this->getKey(),
      'verify' => TRUE,
      'timeout' => $config->get('role_mapping.workgroup_api.timeout') ?: 30,
      'query' => [
        'type' => $type,
        'id' => $id,
      ],
    ];
    $api_url = Settings::get('stanford_samlauth.workgroup_api', self::WORKGROUP_API);
    try {
      $result = $this->guzzle->request('GET', $api_url, $options);
      $result = json_decode($result->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
      $this->responses[$type][$id] = $result;
      return $result;
    }
    catch (GuzzleException $e) {
      $this->logger->error('Unable to connect to workgroup api. @message', ['@message' => $e->getMessage()]);
    }
    return NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function getAllUserWorkgroups(string $authname): array {
    $workgroup_names = [];
    if ($user_data = $this->callApi(NULL, $authname)) {
      foreach ($user_data['results'] as $user_member) {
        $workgroup_names[] = $user_member['name'];
      }
    }
    return $workgroup_names;
  }

}
