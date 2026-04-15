<?php

declare(strict_types=1);

namespace TimeFrontiers;

/**
 * Date and time utilities.
 *
 * Provides convenient date/time formatting and manipulation methods.
 */
class Time {

  // Format constants
  public const MYSQL_DATETIME = 'Y-m-d H:i:s';
  public const MYSQL_DATE = 'Y-m-d';
  public const MYSQL_TIME = 'H:i:s';
  public const ISO8601 = 'c';
  public const RFC2822 = 'r';
  public const ATOM = \DATE_ATOM;

  // Component formats
  public const SHORT_WEEKDAY = 'D';       // Sun through Sat
  public const FULL_WEEKDAY = 'l';        // Sunday through Saturday
  public const DAY_LEADING_ZERO = 'd';    // 01 to 31
  public const DAY = 'j';                 // 1 to 31
  public const DAY_OF_YEAR = 'z';         // 0 to 365
  public const WEEKDAY_NUMBER = 'N';      // 1 (Monday) to 7 (Sunday)
  public const WEEK_OF_YEAR = 'W';        // 01 to 52
  public const SHORT_MONTH = 'M';         // Jan through Dec
  public const FULL_MONTH = 'F';          // January through December
  public const MONTH_NUMBER = 'm';        // 01 to 12
  public const YEAR = 'Y';                // e.g., 2024
  public const SHORT_YEAR = 'y';          // e.g., 24
  public const HOUR_24 = 'H';             // 00 to 23
  public const HOUR_12 = 'g';             // 1 to 12
  public const MINUTE = 'i';              // 00 to 59
  public const SECOND = 's';              // 00 to 59
  public const AM_PM = 'A';               // AM or PM
  public const TIMEZONE = 'T';            // e.g., EST, MDT

  // =========================================================================
  // Current Time
  // =========================================================================

  /**
   * Get current datetime in MySQL format.
   */
  public static function now():string {
    return \date(self::MYSQL_DATETIME);
  }

  /**
   * Get current date in MySQL format.
   */
  public static function today():string {
    return \date(self::MYSQL_DATE);
  }

  /**
   * Get current Unix timestamp.
   */
  public static function timestamp():int {
    return \time();
  }

  // =========================================================================
  // Formatting
  // =========================================================================

  /**
   * Format a datetime string.
   *
   * @param string $format PHP date format
   * @param string|int $datetime Datetime string or Unix timestamp
   * @return string Formatted date
   */
  public static function format(string $format, string|int $datetime = ''):string {
    $unix = self::toTimestamp($datetime);
    return \date($format, $unix);
  }

  /**
   * Get day with ordinal suffix (1st, 2nd, 3rd, 4th, etc.).
   *
   * @param string|int $datetime Datetime string or Unix timestamp
   * @return string Day with suffix (e.g., "21st")
   */
  public static function day(string|int $datetime = ''):string {
    $unix = self::toTimestamp($datetime);
    $day = (int) \date(self::DAY, $unix);

    $suffix = match (true) {
      \in_array($day, [1, 21, 31]) => 'st',
      \in_array($day, [2, 22]) => 'nd',
      \in_array($day, [3, 23]) => 'rd',
      default => 'th',
    };

    return $day . $suffix;
  }

  /**
   * Get month name.
   *
   * @param string|int $datetime Datetime string or Unix timestamp
   * @param bool $short Use short form (Jan vs January)
   * @return string Month name
   */
  public static function month(string|int $datetime = '', bool $short = false):string {
    $unix = self::toTimestamp($datetime);
    $format = $short ? self::SHORT_MONTH : self::FULL_MONTH;
    return \date($format, $unix);
  }

  /**
   * Get year.
   *
   * @param string|int $datetime Datetime string or Unix timestamp
   * @param bool $short Use short form (24 vs 2024)
   * @return string Year
   */
  public static function year(string|int $datetime = '', bool $short = false):string {
    $unix = self::toTimestamp($datetime);
    $format = $short ? self::SHORT_YEAR : self::YEAR;
    return \date($format, $unix);
  }

  /**
   * Get hour.
   *
   * @param string|int $datetime Datetime string or Unix timestamp
   * @param bool $hour_12 Use 12-hour format
   * @return string Hour
   */
  public static function hour(string|int $datetime = '', bool $hour_12 = false):string {
    $unix = self::toTimestamp($datetime);
    $format = $hour_12 ? self::HOUR_12 : self::HOUR_24;
    return \date($format, $unix);
  }

  /**
   * Get weekday name.
   *
   * @param string|int $datetime Datetime string or Unix timestamp
   * @param bool $short Use short form (Sun vs Sunday)
   * @return string Weekday name
   */
  public static function weekday(string|int $datetime = '', bool $short = false):string {
    $unix = self::toTimestamp($datetime);
    $format = $short ? self::SHORT_WEEKDAY : self::FULL_WEEKDAY;
    return \date($format, $unix);
  }

  /**
   * Get week number of the year.
   *
   * @param string|int $datetime Datetime string or Unix timestamp
   * @return int Week number (1-52)
   */
  public static function week(string|int $datetime = ''):int {
    $unix = self::toTimestamp($datetime);
    return (int) \date(self::WEEK_OF_YEAR, $unix);
  }

  // =========================================================================
  // Combined Formats
  // =========================================================================

  /**
   * Get "Month Day" format (e.g., "April 7th").
   *
   * @param string|int $datetime Datetime string or Unix timestamp
   * @param bool $short Use short month name
   * @return string Formatted date
   */
  public static function monthDay(string|int $datetime = '', bool $short = false):string {
    return self::month($datetime, $short) . ' ' . self::day($datetime);
  }

  /**
   * Get "Month Day, Year" format (e.g., "April 7th, 2024").
   *
   * @param string|int $datetime Datetime string or Unix timestamp
   * @param bool $short Use short month/year names
   * @return string Formatted date
   */
  public static function mdy(string|int $datetime = '', bool $short = false):string {
    return self::monthDay($datetime, $short) . ', ' . self::year($datetime, $short);
  }

  /**
   * Get "Hour:Minute:Second" format (e.g., "14:30:00").
   *
   * @param string|int $datetime Datetime string or Unix timestamp
   * @return string Formatted time
   */
  public static function hms(string|int $datetime = ''):string {
    $unix = self::toTimestamp($datetime);
    return \date(self::HOUR_24 . ':' . self::MINUTE . ':' . self::SECOND, $unix);
  }

  /**
   * Get "Hour:Minute" format (e.g., "14:30").
   *
   * @param string|int $datetime Datetime string or Unix timestamp
   * @param bool $hour_12 Use 12-hour format with AM/PM
   * @return string Formatted time
   */
  public static function hm(string|int $datetime = '', bool $hour_12 = false):string {
    $unix = self::toTimestamp($datetime);

    if ($hour_12) {
      return \date(self::HOUR_12 . ':' . self::MINUTE . ' ' . self::AM_PM, $unix);
    }

    return \date(self::HOUR_24 . ':' . self::MINUTE, $unix);
  }

  /**
   * Get full datetime string (e.g., "April 7th, 2024 at 14:30:00").
   *
   * @param string|int $datetime Datetime string or Unix timestamp
   * @return string Formatted datetime
   */
  public static function dateTym(string|int $datetime = ''):string {
    return self::mdy($datetime) . ' at ' . self::hms($datetime);
  }

  /**
   * Get weekday with date (e.g., "Sunday, 7th April 2024").
   *
   * @param string|int $datetime Datetime string or Unix timestamp
   * @param bool $short Use short weekday/month names
   * @return string Formatted date
   */
  public static function weekDateTym(string|int $datetime = '', bool $short = false):string {
    return self::weekday($datetime, $short) . ', '
      . self::day($datetime) . ' '
      . self::month($datetime, $short) . ' '
      . self::year($datetime);
  }

  // =========================================================================
  // Relative Time
  // =========================================================================

  /**
   * Get human-readable relative time (e.g., "2 hours ago", "in 3 days").
   *
   * @param string|int $datetime Datetime string or Unix timestamp
   * @param string|int $relative_to Base datetime (default: now)
   * @return string Relative time string
   */
  public static function relative(string|int $datetime, string|int $relative_to = ''):string {
    $unix = self::toTimestamp($datetime);
    $base = self::toTimestamp($relative_to);
    $diff = $base - $unix;
    $abs_diff = \abs($diff);
    $is_past = $diff > 0;

    $intervals = [
      ['year', 31536000],
      ['month', 2592000],
      ['week', 604800],
      ['day', 86400],
      ['hour', 3600],
      ['minute', 60],
      ['second', 1],
    ];

    foreach ($intervals as [$name, $seconds]) {
      $count = \floor($abs_diff / $seconds);

      if ($count >= 1) {
        $plural = $count > 1 ? 's' : '';
        $time_str = "{$count} {$name}{$plural}";

        return $is_past ? "{$time_str} ago" : "in {$time_str}";
      }
    }

    return 'just now';
  }

  // =========================================================================
  // Calculations
  // =========================================================================

  /**
   * Add time to a datetime.
   *
   * @param string $interval DateInterval format (e.g., "P1D", "PT2H")
   * @param string|int $datetime Base datetime
   * @return string New datetime in MySQL format
   */
  public static function add(string $interval, string|int $datetime = ''):string {
    $date = new \DateTime();
    $date->setTimestamp(self::toTimestamp($datetime));
    $date->add(new \DateInterval($interval));

    return $date->format(self::MYSQL_DATETIME);
  }

  /**
   * Subtract time from a datetime.
   *
   * @param string $interval DateInterval format (e.g., "P1D", "PT2H")
   * @param string|int $datetime Base datetime
   * @return string New datetime in MySQL format
   */
  public static function sub(string $interval, string|int $datetime = ''):string {
    $date = new \DateTime();
    $date->setTimestamp(self::toTimestamp($datetime));
    $date->sub(new \DateInterval($interval));

    return $date->format(self::MYSQL_DATETIME);
  }

  /**
   * Get difference between two datetimes.
   *
   * @param string|int $datetime1 First datetime
   * @param string|int $datetime2 Second datetime (default: now)
   * @return \DateInterval Difference
   */
  public static function diff(string|int $datetime1, string|int $datetime2 = ''):\DateInterval {
    $date1 = new \DateTime();
    $date1->setTimestamp(self::toTimestamp($datetime1));

    $date2 = new \DateTime();
    $date2->setTimestamp(self::toTimestamp($datetime2));

    return $date1->diff($date2);
  }

  /**
   * Get difference in seconds.
   *
   * @param string|int $datetime1 First datetime
   * @param string|int $datetime2 Second datetime (default: now)
   * @return int Seconds difference (can be negative)
   */
  public static function diffSeconds(string|int $datetime1, string|int $datetime2 = ''):int {
    return self::toTimestamp($datetime2) - self::toTimestamp($datetime1);
  }

  // =========================================================================
  // Comparisons
  // =========================================================================

  /**
   * Check if datetime is in the past.
   */
  public static function isPast(string|int $datetime):bool {
    return self::toTimestamp($datetime) < \time();
  }

  /**
   * Check if datetime is in the future.
   */
  public static function isFuture(string|int $datetime):bool {
    return self::toTimestamp($datetime) > \time();
  }

  /**
   * Check if datetime is today.
   */
  public static function isToday(string|int $datetime):bool {
    $unix = self::toTimestamp($datetime);
    return \date(self::MYSQL_DATE, $unix) === \date(self::MYSQL_DATE);
  }

  /**
   * Check if two datetimes are the same day.
   */
  public static function isSameDay(string|int $datetime1, string|int $datetime2):bool {
    $unix1 = self::toTimestamp($datetime1);
    $unix2 = self::toTimestamp($datetime2);

    return \date(self::MYSQL_DATE, $unix1) === \date(self::MYSQL_DATE, $unix2);
  }

  // =========================================================================
  // Conversion
  // =========================================================================

  /**
   * Convert datetime string or timestamp to Unix timestamp.
   *
   * @param string|int $datetime Datetime string or Unix timestamp
   * @return int Unix timestamp
   * @throws \InvalidArgumentException If datetime cannot be parsed
   */
  public static function toTimestamp(string|int $datetime = ''):int {
    if (empty($datetime)) {
      return \time();
    }

    if (\is_int($datetime)) {
      return $datetime;
    }

    // Check if numeric string (Unix timestamp)
    if (\is_numeric($datetime)) {
      return (int) $datetime;
    }

    $timestamp = \strtotime($datetime);

    if ($timestamp === false) {
      throw new \InvalidArgumentException("Invalid datetime: {$datetime}");
    }

    return $timestamp;
  }

  /**
   * Convert to DateTime object.
   *
   * @param string|int $datetime Datetime string or Unix timestamp
   * @return \DateTime DateTime object
   */
  public static function toDateTime(string|int $datetime = ''):\DateTime {
    $obj = new \DateTime();
    $obj->setTimestamp(self::toTimestamp($datetime));

    return $obj;
  }

  /**
   * Convert to MySQL datetime format.
   *
   * @param string|int $datetime Datetime string or Unix timestamp
   * @return string MySQL datetime string
   */
  public static function toMysql(string|int $datetime = ''):string {
    return \date(self::MYSQL_DATETIME, self::toTimestamp($datetime));
  }

  /**
   * Convert to ISO 8601 format.
   *
   * @param string|int $datetime Datetime string or Unix timestamp
   * @return string ISO 8601 datetime string
   */
  public static function toIso(string|int $datetime = ''):string {
    return \date(self::ISO8601, self::toTimestamp($datetime));
  }
}

// ============================================================================
// Global function for backward compatibility
// ============================================================================

if (!\function_exists('tym')) {
  /**
   * Get current Unix timestamp.
   * @return int
   */
  function tym():int {
    return \time();
  }
}
