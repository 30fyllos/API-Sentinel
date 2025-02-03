<?php

namespace Drupal\api_sentinel\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for displaying API key usage.
 */
class ApiSentinelUsageController extends ControllerBase {

  /**
   * Database connection.
   *
   * @var Connection
   */
  protected Connection $database;

  /**
   * Constructs a new API Sentinel Usage Controller.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Displays API usage statistics in a dialog.
   */
  public function usageDialog($key_id): array
  {
    $query = $this->database->select('api_sentinel_usage', 'asu')
      ->fields('asu', ['key_id']);
//      ->addExpression('MAX(used_at)', 'last_used')
//      ->addExpression("SUM(CASE WHEN asu.status = 1 THEN 1 ELSE 0 END)", 'success_count')
//      ->addExpression("SUM(CASE WHEN asu.status = 0 THEN 1 ELSE 0 END)", 'failed_count')
//      ->condition('key_id', $key_id)
//      ->groupBy('key_id')
//      ->execute();
//      ->fetchAll();
    $query->addExpression()
    dd($query);

    $rows = [];
    foreach ($query as $record) {
      $rows[] = [
        'last_used' => !empty($record->last_used) ? date('Y-m-d H:i:s', $record->last_used) : '-',
        'success_count' => $record->success_count ?? 0,
        'failed_count' => $record->failed_count ?? 0,
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Last Used'),
        $this->t('Succeeded'),
        $this->t('Failed'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No API usage data found for this key.'),
    ];
  }

}
