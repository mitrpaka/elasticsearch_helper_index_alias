<?php

namespace Drupal\elasticsearch_helper_index_alias\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\elasticsearch_helper_index_alias\AliasServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Elasticsearch\Client;

/**
 * Class ManageAliasController.
 *
 * The controller displays the current state of the aliases and index versions.
 */
class ManageAliasController extends ControllerBase {

  /**
   * Elasticsearch\Client definition.
   *
   * @var \Elasticsearch\Client
   */
  protected $client;

  /**
   * Drupal\elasticsearch_helper_index_alias\AliasServiceInterface definition.
   *
   * @var \Drupal\elasticsearch_helper_index_alias\AliasServiceInterface
   */
  protected $aliasService;

  /**
   * Constructs a new ManagementController object.
   */
  public function __construct(Client $client, AliasServiceInterface $alias_service) {
    $this->client = $client;
    $this->aliasService = $alias_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('elasticsearch_helper.elasticsearch_client'),
      $container->get('elasticsearch_helper_index_alias.service')
    );
  }

  /**
   * Lists all the indices status.
   *
   * @return array
   *   Renderable array
   */
  public function indicesStatus(): array {
    $response = $this->client->cat()->indices();

    $header = [
      $this->t('Name'),
      $this->t('Documents'),
      $this->t('Size'),
      $this->t('Health'),
      $this->t('Actions'),
    ];

    // Sort by index name.
    usort($response, function ($a, $b) {
      return $a['index'] <=> $b['index'];
    });

    $rows = [];

    foreach ($response as $item) {
      $count = $this->client->count(['index' => $item['index']]);

      $rows[] = [
        $item['index'],
        $count['count'],
        $item['store.size'],
        $item['health'],
        Link::createFromRoute(
          $this->t('Delete Index'),
          'elasticsearch_helper_index_alias.delete_index_confirm_form',
          ['index' => $item['index']],
          ['attributes' => ['class' => 'button']]
        ),
      ];
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];
  }

  /**
   * Lists the current active aliases and their destination index.
   *
   * @return array
   *   Renderable array
   */
  public function aliases(): array {
    $version = $this->aliasService->getCurrentVersion();
    $aliases = $this->client->cat()->aliases();

    $rows = [];

    foreach ($aliases as $alias) {
      $rows[] = [
        $alias['alias'],
        $alias['index'],
      ];
    }

    return [
      '#caption' => $this->t('Current index version: @version', ['@version' => $version]),
      '#type' => 'table',
      '#header' => [$this->t('Name'), $this->t('Destination Index')],
      '#rows' => $rows,
    ];
  }

}
