<?php

namespace Drupal\api_sentinel\Enum;

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
   * Get the corresponding timestamp for the timeframe.
   *
   * This method computes the timestamp relative to the current time.
   *
   * @return int
   *   The computed timestamp.
   */
  public function toTimestamp(): int {
    return match ($this) {
      self::ONE_HOUR    => strtotime('-1 hour'),
      self::TWO_HOURS   => strtotime('-2 hours'),
      self::THREE_HOURS => strtotime('-3 hours'),
      self::SIX_HOURS   => strtotime('-6 hours'),
      self::ONE_DAY     => strtotime('-1 day'),
      self::SEVEN_DAYS  => strtotime('-7 days'),
      self::THIRTY_DAYS => strtotime('-30 days'),
    };
  }

  /**
   * Get the full, human-readable name of the timeframe.
   *
   * @return string
   *   The name of the timeframe.
   */
  public function toName(): string {
    return match ($this) {
      self::ONE_HOUR    => '1 hour',
      self::TWO_HOURS   => '2 hours',
      self::THREE_HOURS => '3 hours',
      self::SIX_HOURS   => '6 hours',
      self::ONE_DAY     => '1 day',
      self::SEVEN_DAYS  => '7 days',
      self::THIRTY_DAYS => '30 days',
    };
  }

  /**
   * Convert a string value into a Timeframe enum.
   *
   * @param string $value
   *   The string representation (e.g. "1h").
   *
   * @return static|null
   *   The corresponding Timeframe enum, or NULL if invalid.
   */
  public static function fromString(string $value): ?self {
    return self::tryFrom($value);
  }

  /**
   * Get both the timestamp and the full name from a timeframe string.
   *
   * @param string $value
   *   The string representation of the timeframe.
   *
   * @return array|null
   *   An associative array with 'timeframe', 'name', and 'timestamp'
   *   keys, or NULL if the provided value is invalid.
   */
  public static function getDetails(string $value): ?array {
    $timeframe = self::fromString($value);
    return $timeframe ? [
      'timeframe' => $timeframe->value,       // e.g. "1h"
      'name'      => $timeframe->toName(),      // e.g. "1 hour"
      'timestamp' => $timeframe->toTimestamp(), // computed dynamically
    ] : null;
  }

  /**
   * Get the options array for a select field.
   *
   * @return array
   *   An associative array where the key is the enum value (e.g. "1h")
   *   and the value is the human-readable name (e.g. "1 hour").
   */
  public static function options(): array {
    $options = [];
    foreach (self::cases() as $timeframe) {
      $options[$timeframe->value] = $timeframe->toName();
    }
    return $options;
  }
}
