<?php

namespace Drupal\api_sentinel\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\api_sentinel\Enum\Timeframe;

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
  public function usageDialog($key_id, $timeframe = '7d'): array
  {
    $timeCondition = Timeframe::fromString($timeframe)?->toTimestamp();

    $query = $this->database->select('api_sentinel_usage', 'asu');
    $query->fields('asu', ['key_id']);
    $query->addExpression('MAX(used_at)', 'last_used');
    $query->addExpression("SUM(CASE WHEN asu.status = 1 THEN 1 ELSE 0 END)", 'success_count');
    $query->addExpression("SUM(CASE WHEN asu.status = 0 THEN 1 ELSE 0 END)", 'failed_count');
    $query->condition('key_id', $key_id);
    $query->groupBy('key_id');

    if ($timeCondition) {
      $query->condition('used_at', $timeCondition, '>');
    }

    $results = $query->execute()->fetchAll();

    $rows = [];
    foreach ($results as $record) {
      $rows[] = [
        'last_used' => !empty($record->last_used) ? date('Y-m-d H:i:s', $record->last_used) : '-',
        'success_count' => $record->success_count ?? 0,
        'failed_count' => $record->failed_count ?? 0,
      ];
    }

    $output['container'] = [
      '#type' => 'container'
    ];

    $timeConditionName = Timeframe::fromString($timeframe)?->toName();
    $output['container']['intro'] = [
      '#markup' => "<h4>{$this->t('Activity in the last:')} <strong>{$timeConditionName}</strong></h4>",
    ];

    $output['container']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Last Used'),
        $this->t('Successful Requests'),
        $this->t('Failed Requests'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No API usage data found for this key.'),
    ];

    return $output;
  }

}
