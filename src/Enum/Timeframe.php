<?php

namespace Drupal\api_sentinel\Enum;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheFactory;
use Drupal::service;

/**
 * Enum representing valid timeframes for API key usage queries.
 */
enum Timeframe: string {
  case ONE_HOUR = '1h';
  case TWO_HOURS = '2h';
  case THREE_HOURS = '3h';
  case SIX_HOURS = '6h';
  case ONE_DAY = '1d';
  case SEVEN_DAYS = '7d';
  case THIRTY_DAYS = '30d';

  /**
   * Get the cache service.
   */
  private static function cache(): CacheBackendInterface {
    return service('cache.default');
  }

  /**
   * Get the corresponding timestamp for the timeframe, using cache.
   */
  public function toTimestamp(): int {
    $cacheKey = "timeframe_timestamp:{$this->value}";
    $cache = self::cache()->get($cacheKey);

    if ($cache) {
      return $cache->data;
    }

    $timestamp = match ($this) {
      self::ONE_HOUR => strtotime('-1 hour'),
      self::TWO_HOURS => strtotime('-2 hours'),
      self::THREE_HOURS => strtotime('-3 hours'),
      self::SIX_HOURS => strtotime('-6 hours'),
      self::ONE_DAY => strtotime('-1 day'),
      self::SEVEN_DAYS => strtotime('-7 days'),
      self::THIRTY_DAYS => strtotime('-30 days'),
    };

    // Cache the timestamp for future use
    self::cache()->set($cacheKey, $timestamp, CacheBackendInterface::CACHE_PERMANENT);

    return $timestamp;
  }

  /**
   * Get the full name of the timeframe.
   */
  public function toName(): string {
    return match ($this) {
      self::ONE_HOUR => '1 hour',
      self::TWO_HOURS => '2 hours',
      self::THREE_HOURS => '3 hours',
      self::SIX_HOURS => '6 hours',
      self::ONE_DAY => '1 day',
      self::SEVEN_DAYS => '7 days',
      self::THIRTY_DAYS => '30 days',
    };
  }

  /**
   * Convert a string value into a Timeframe enum.
   */
  public static function fromString(string $value): ?self {
    return self::tryFrom($value);
  }

  /**
   * Get both timestamp & full name from a timeframe string.
   */
  public static function getDetails(string $value): ?array {
    $timeframe = self::fromString($value);

    return $timeframe ? [
      'timeframe' => $timeframe->value,  // "1h"
      'name' => $timeframe->toName(),    // "1 hour"
      'timestamp' => $timeframe->toTimestamp(),  // Cached timestamp
    ] : null;
  }
}
