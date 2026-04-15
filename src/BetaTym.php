<?php

declare(strict_types=1);

namespace TimeFrontiers;

/**
 * Legacy BetaTym class for backward compatibility.
 *
 * @deprecated Use Time class instead.
 */
class BetaTym {

  // Format constants (preserved for compatibility)
  public const MYSQL_DATETYM_STRING = Time::MYSQL_DATETIME;
  public const MYSQL_DATETIME_STRING = Time::MYSQL_DATETIME;
  public const SHORT_WEEK_DAY = Time::SHORT_WEEKDAY;
  public const FULL_WEEK_DAY = Time::FULL_WEEKDAY;
  public const DAY_LEADING_ZERO = Time::DAY_LEADING_ZERO;
  public const DAY = Time::DAY;
  public const DAY_OF_YEAR_WITH = Time::DAY_OF_YEAR;
  public const WEEK_DAY_NUMBER = Time::WEEKDAY_NUMBER;
  public const WEEK_OF_YEAR = Time::WEEK_OF_YEAR;
  public const SHORT_MONTH_NAME = Time::SHORT_MONTH;
  public const MONTH_NAME = Time::FULL_MONTH;
  public const MONTH_NUMBER = Time::MONTH_NUMBER;
  public const YEAR = Time::YEAR;
  public const SHORT_YEAR_NUMBER = Time::SHORT_YEAR;
  public const FULL_HOUR = Time::HOUR_24;
  public const HALF_HOUR = Time::HOUR_12;
  public const MINUTE = Time::MINUTE;
  public const AM_PM = Time::AM_PM;
  public const SECOND = Time::SECOND;

  /**
   * @deprecated Use Time::now()
   */
  public static function now():string {
    return Time::now();
  }

  /**
   * @deprecated Use Time::format()
   */
  public static function get(string $format, string $tym = ''):string {
    return Time::format($format, $tym);
  }

  /**
   * @deprecated Use Time::day()
   */
  public static function day(string $dateTym = ''):string {
    return Time::day($dateTym);
  }

  /**
   * @deprecated Use Time::month()
   */
  public static function month(string $dateTym = '', bool $short_form = false):string {
    return Time::month($dateTym, $short_form);
  }

  /**
   * @deprecated Use Time::year()
   */
  public static function year(string $dateTym = '', bool $short_form = false):string {
    return Time::year($dateTym, $short_form);
  }

  /**
   * @deprecated Use Time::hour()
   */
  public static function hour(string $dateTym = '', bool $hour_12 = false):string {
    return Time::hour($dateTym, $hour_12);
  }

  /**
   * @deprecated Use Time::monthDay()
   */
  public static function monthDay(string $dateTym = '', bool $short_form = false):string {
    return Time::monthDay($dateTym, $short_form);
  }

  /**
   * @deprecated Use Time::mdy()
   */
  public static function MDY(string $dateTym = '', bool $short_form = false):string {
    return Time::mdy($dateTym, $short_form);
  }

  /**
   * @deprecated Use Time::hms()
   */
  public static function HMS(string $dateTym = ''):string {
    return Time::hms($dateTym);
  }

  /**
   * @deprecated Use Time::dateTym()
   */
  public static function dateTym(string $dateTym = ''):string {
    return Time::dateTym($dateTym);
  }

  /**
   * @deprecated Use Time::week()
   */
  public static function week(string $dateTym = ''):int {
    return Time::week($dateTym);
  }

  /**
   * @deprecated Use Time::weekday()
   */
  public static function weekDay(string $dateTym = '', bool $short_form = false):string {
    return Time::weekday($dateTym, $short_form);
  }

  /**
   * @deprecated Use Time::weekDateTym()
   */
  public static function weekDateTym(string $dateTym = '', bool $short_form = false):string {
    return Time::weekDateTym($dateTym, $short_form);
  }

  /**
   * @deprecated Use Time::toTimestamp()
   */
  public static function seconds(string|int $tym):int {
    return Time::toTimestamp($tym);
  }
}
