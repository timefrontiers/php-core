<?php

declare(strict_types=1);

namespace TimeFrontiers;

use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\NumberParseException;
use libphonenumber\geocoding\PhoneNumberOfflineGeocoder;
use libphonenumber\PhoneNumberToCarrierMapper;
use libphonenumber\PhoneNumberToTimeZonesMapper;

/**
 * Phone number utilities.
 *
 * Thin, static, return-null wrapper around giggsey/libphonenumber-for-php.
 * All methods return null/false on failure — no exceptions thrown.
 *
 * @see https://github.com/giggsey/libphonenumber-for-php
 */
class Phone {

  // Format constants (mirror libphonenumber\PhoneNumberFormat)
  public const FORMAT_E164          = 'E164';          // +2348031234567
  public const FORMAT_INTERNATIONAL = 'INTERNATIONAL'; // +234 803 123 4567
  public const FORMAT_NATIONAL      = 'NATIONAL';      // 0803 123 4567
  public const FORMAT_RFC3966       = 'RFC3966';       // tel:+234-803-123-4567

  // Line type constants returned by type()
  public const TYPE_MOBILE          = 'mobile';
  public const TYPE_FIXED_LINE      = 'landline';
  public const TYPE_FIXED_OR_MOBILE = 'fixed_or_mobile';
  public const TYPE_TOLL_FREE       = 'toll_free';
  public const TYPE_PREMIUM_RATE    = 'premium_rate';
  public const TYPE_SHARED_COST     = 'shared_cost';
  public const TYPE_VOIP            = 'voip';
  public const TYPE_PERSONAL_NUMBER = 'personal_number';
  public const TYPE_PAGER           = 'pager';
  public const TYPE_UAN             = 'uan';
  public const TYPE_VOICEMAIL       = 'voicemail';
  public const TYPE_UNKNOWN         = 'unknown';

  /**
   * ISO 3166-1 alpha-2 country code → continent name.
   * Covers all 249 assigned codes. "AQ" (Antarctica) is its own continent.
   */
  private const CONTINENT_MAP = [
    // Africa
    'DZ' => 'Africa', 'AO' => 'Africa', 'BJ' => 'Africa', 'BW' => 'Africa',
    'BF' => 'Africa', 'BI' => 'Africa', 'CV' => 'Africa', 'CM' => 'Africa',
    'CF' => 'Africa', 'TD' => 'Africa', 'KM' => 'Africa', 'CG' => 'Africa',
    'CD' => 'Africa', 'CI' => 'Africa', 'DJ' => 'Africa', 'EG' => 'Africa',
    'GQ' => 'Africa', 'ER' => 'Africa', 'SZ' => 'Africa', 'ET' => 'Africa',
    'GA' => 'Africa', 'GM' => 'Africa', 'GH' => 'Africa', 'GN' => 'Africa',
    'GW' => 'Africa', 'KE' => 'Africa', 'LS' => 'Africa', 'LR' => 'Africa',
    'LY' => 'Africa', 'MG' => 'Africa', 'MW' => 'Africa', 'ML' => 'Africa',
    'MR' => 'Africa', 'MU' => 'Africa', 'YT' => 'Africa', 'MA' => 'Africa',
    'MZ' => 'Africa', 'NA' => 'Africa', 'NE' => 'Africa', 'NG' => 'Africa',
    'RE' => 'Africa', 'RW' => 'Africa', 'SH' => 'Africa', 'ST' => 'Africa',
    'SN' => 'Africa', 'SC' => 'Africa', 'SL' => 'Africa', 'SO' => 'Africa',
    'ZA' => 'Africa', 'SS' => 'Africa', 'SD' => 'Africa', 'TZ' => 'Africa',
    'TG' => 'Africa', 'TN' => 'Africa', 'UG' => 'Africa', 'EH' => 'Africa',
    'ZM' => 'Africa', 'ZW' => 'Africa',

    // Europe
    'AX' => 'Europe', 'AL' => 'Europe', 'AD' => 'Europe', 'AT' => 'Europe',
    'BY' => 'Europe', 'BE' => 'Europe', 'BA' => 'Europe', 'BG' => 'Europe',
    'HR' => 'Europe', 'CY' => 'Europe', 'CZ' => 'Europe', 'DK' => 'Europe',
    'EE' => 'Europe', 'FO' => 'Europe', 'FI' => 'Europe', 'FR' => 'Europe',
    'DE' => 'Europe', 'GI' => 'Europe', 'GR' => 'Europe', 'GG' => 'Europe',
    'VA' => 'Europe', 'HU' => 'Europe', 'IS' => 'Europe', 'IE' => 'Europe',
    'IM' => 'Europe', 'IT' => 'Europe', 'JE' => 'Europe', 'XK' => 'Europe',
    'LV' => 'Europe', 'LI' => 'Europe', 'LT' => 'Europe', 'LU' => 'Europe',
    'MT' => 'Europe', 'MD' => 'Europe', 'MC' => 'Europe', 'ME' => 'Europe',
    'NL' => 'Europe', 'MK' => 'Europe', 'NO' => 'Europe', 'PL' => 'Europe',
    'PT' => 'Europe', 'RO' => 'Europe', 'RU' => 'Europe', 'SM' => 'Europe',
    'RS' => 'Europe', 'SK' => 'Europe', 'SI' => 'Europe', 'ES' => 'Europe',
    'SJ' => 'Europe', 'SE' => 'Europe', 'CH' => 'Europe', 'UA' => 'Europe',
    'GB' => 'Europe',

    // Asia
    'AF' => 'Asia', 'AM' => 'Asia', 'AZ' => 'Asia', 'BH' => 'Asia',
    'BD' => 'Asia', 'BT' => 'Asia', 'IO' => 'Asia', 'BN' => 'Asia',
    'KH' => 'Asia', 'CN' => 'Asia', 'GE' => 'Asia', 'HK' => 'Asia',
    'IN' => 'Asia', 'ID' => 'Asia', 'IR' => 'Asia', 'IQ' => 'Asia',
    'IL' => 'Asia', 'JP' => 'Asia', 'JO' => 'Asia', 'KZ' => 'Asia',
    'KP' => 'Asia', 'KR' => 'Asia', 'KW' => 'Asia', 'KG' => 'Asia',
    'LA' => 'Asia', 'LB' => 'Asia', 'MO' => 'Asia', 'MY' => 'Asia',
    'MV' => 'Asia', 'MN' => 'Asia', 'MM' => 'Asia', 'NP' => 'Asia',
    'OM' => 'Asia', 'PK' => 'Asia', 'PS' => 'Asia', 'PH' => 'Asia',
    'QA' => 'Asia', 'SA' => 'Asia', 'SG' => 'Asia', 'LK' => 'Asia',
    'SY' => 'Asia', 'TW' => 'Asia', 'TJ' => 'Asia', 'TH' => 'Asia',
    'TL' => 'Asia', 'TR' => 'Asia', 'TM' => 'Asia', 'AE' => 'Asia',
    'UZ' => 'Asia', 'VN' => 'Asia', 'YE' => 'Asia',

    // North America
    'AI' => 'North America', 'AG' => 'North America', 'AW' => 'North America',
    'BS' => 'North America', 'BB' => 'North America', 'BZ' => 'North America',
    'BM' => 'North America', 'BQ' => 'North America', 'CA' => 'North America',
    'KY' => 'North America', 'CR' => 'North America', 'CU' => 'North America',
    'CW' => 'North America', 'DM' => 'North America', 'DO' => 'North America',
    'SV' => 'North America', 'GL' => 'North America', 'GD' => 'North America',
    'GP' => 'North America', 'GT' => 'North America', 'HT' => 'North America',
    'HN' => 'North America', 'JM' => 'North America', 'MQ' => 'North America',
    'MX' => 'North America', 'MS' => 'North America', 'NI' => 'North America',
    'PA' => 'North America', 'PR' => 'North America', 'BL' => 'North America',
    'KN' => 'North America', 'LC' => 'North America', 'MF' => 'North America',
    'PM' => 'North America', 'VC' => 'North America', 'SX' => 'North America',
    'TT' => 'North America', 'TC' => 'North America', 'US' => 'North America',
    'VG' => 'North America', 'VI' => 'North America',

    // South America
    'AR' => 'South America', 'BO' => 'South America', 'BR' => 'South America',
    'CL' => 'South America', 'CO' => 'South America', 'EC' => 'South America',
    'FK' => 'South America', 'GF' => 'South America', 'GY' => 'South America',
    'PY' => 'South America', 'PE' => 'South America', 'SR' => 'South America',
    'UY' => 'South America', 'VE' => 'South America',

    // Oceania
    'AS' => 'Oceania', 'AU' => 'Oceania', 'CX' => 'Oceania', 'CC' => 'Oceania',
    'CK' => 'Oceania', 'FJ' => 'Oceania', 'PF' => 'Oceania', 'GU' => 'Oceania',
    'KI' => 'Oceania', 'MH' => 'Oceania', 'FM' => 'Oceania', 'NR' => 'Oceania',
    'NC' => 'Oceania', 'NZ' => 'Oceania', 'NU' => 'Oceania', 'NF' => 'Oceania',
    'MP' => 'Oceania', 'PW' => 'Oceania', 'PG' => 'Oceania', 'PN' => 'Oceania',
    'WS' => 'Oceania', 'SB' => 'Oceania', 'TK' => 'Oceania', 'TO' => 'Oceania',
    'TV' => 'Oceania', 'UM' => 'Oceania', 'VU' => 'Oceania', 'WF' => 'Oceania',

    // Antarctica
    'AQ' => 'Antarctica', 'BV' => 'Antarctica', 'GS' => 'Antarctica',
    'HM' => 'Antarctica', 'TF' => 'Antarctica',
  ];

  // =========================================================================
  // Formatting
  // =========================================================================

  /**
   * Format a phone number in any supported format.
   *
   * @param string $phone Raw phone number (with or without country code).
   * @param string $format One of the FORMAT_* constants. Defaults to E.164.
   * @param string|null $region ISO alpha-2 region hint for parsing local numbers (e.g. "NG").
   * @return string|null Formatted number, or null if parsing/validation fails.
   */
  public static function format(string $phone, string $format = self::FORMAT_E164, ?string $region = null):?string {
    $parsed = self::_parse($phone, $region);
    if ($parsed === null) {
      return null;
    }

    $util = PhoneNumberUtil::getInstance();
    if (!$util->isValidNumber($parsed)) {
      return null;
    }

    $fmt = match (\strtoupper($format)) {
      self::FORMAT_E164          => PhoneNumberFormat::E164,
      self::FORMAT_INTERNATIONAL => PhoneNumberFormat::INTERNATIONAL,
      self::FORMAT_NATIONAL      => PhoneNumberFormat::NATIONAL,
      self::FORMAT_RFC3966       => PhoneNumberFormat::RFC3966,
      default                    => null,
    };

    if ($fmt === null) {
      return null;
    }

    return $util->format($parsed, $fmt);
  }

  /**
   * Format as E.164 (e.g. "+2348031234567"). The canonical storage format.
   */
  public static function toE164(string $phone, ?string $region = null):?string {
    return self::format($phone, self::FORMAT_E164, $region);
  }

  /**
   * Format as international with spaces (e.g. "+234 803 123 4567").
   */
  public static function toIntl(string $phone, ?string $region = null):?string {
    return self::format($phone, self::FORMAT_INTERNATIONAL, $region);
  }

  /**
   * Format as national / local (e.g. "0803 123 4567").
   * Requires knowing the region (either from the +prefix or the $region arg).
   */
  public static function toNational(string $phone, ?string $region = null):?string {
    return self::format($phone, self::FORMAT_NATIONAL, $region);
  }

  /**
   * Alias of toNational().
   */
  public static function toLocal(string $phone, ?string $region = null):?string {
    return self::toNational($phone, $region);
  }

  /**
   * Format as an RFC3966 "tel:" URI (e.g. "tel:+234-803-123-4567"). Useful for
   * <a href="…"> click-to-call links.
   */
  public static function toRfc3966(string $phone, ?string $region = null):?string {
    return self::format($phone, self::FORMAT_RFC3966, $region);
  }

  // =========================================================================
  // Country / continent lookup
  // =========================================================================

  /**
   * ISO 3166-1 alpha-2 country code of the number (e.g. "NG", "US").
   *
   * @return string|null Two-letter uppercase code, or null if undetermined.
   */
  public static function country(string $phone, ?string $region = null):?string {
    $parsed = self::_parse($phone, $region);
    if ($parsed === null) {
      return null;
    }

    $util = PhoneNumberUtil::getInstance();
    $code = $util->getRegionCodeForNumber($parsed);

    return ($code && $code !== 'ZZ') ? $code : null;
  }

  /**
   * Full country name in English (e.g. "Nigeria").
   * Uses the offline geocoder to get the display name.
   */
  public static function countryName(string $phone, string $locale = 'en', ?string $region = null):?string {
    $iso = self::country($phone, $region);
    if ($iso === null) {
      return null;
    }

    // PHP intl extension gives us the most accurate localized name.
    if (\class_exists(\Locale::class) && \function_exists('locale_get_display_region')) {
      $name = \Locale::getDisplayRegion('-' . $iso, $locale);
      if (!empty($name) && $name !== $iso) {
        return $name;
      }
    }

    // Fallback: use the libphonenumber geocoder description for the full number.
    $parsed = self::_parse($phone, $region);
    if ($parsed !== null) {
      $geo = PhoneNumberOfflineGeocoder::getInstance();
      $desc = $geo->getDescriptionForNumber($parsed, $locale);
      if (!empty($desc)) {
        return $desc;
      }
    }

    return $iso;
  }

  /**
   * International dialling code / country calling code (e.g. 234 for Nigeria).
   *
   * @return int|null Integer dial code, or null if undetermined.
   */
  public static function dialCode(string $phone, ?string $region = null):?int {
    $parsed = self::_parse($phone, $region);
    if ($parsed === null) {
      return null;
    }

    $code = $parsed->getCountryCode();
    return $code !== null ? (int)$code : null;
  }

  /**
   * Continent name (e.g. "Africa", "Europe", "North America").
   *
   * Based on ISO 3166-1 alpha-2 → continent mapping covering all 249 codes.
   *
   * @return string|null Continent name, or null if the country can't be detected.
   */
  public static function continent(string $phone, ?string $region = null):?string {
    $iso = self::country($phone, $region);
    if ($iso === null) {
      return null;
    }

    return self::CONTINENT_MAP[$iso] ?? null;
  }

  /**
   * Resolve continent from an ISO alpha-2 country code directly (no phone parsing).
   */
  public static function continentFromCountry(string $iso):?string {
    return self::CONTINENT_MAP[\strtoupper($iso)] ?? null;
  }

  // =========================================================================
  // Number type, carrier, timezones, geocoding
  // =========================================================================

  /**
   * Classify the line type: mobile / landline / toll_free / voip / etc.
   *
   * @return string|null One of the TYPE_* constants, or null if unparseable.
   */
  public static function type(string $phone, ?string $region = null):?string {
    $parsed = self::_parse($phone, $region);
    if ($parsed === null) {
      return null;
    }

    $util = PhoneNumberUtil::getInstance();
    return match ($util->getNumberType($parsed)) {
      PhoneNumberType::MOBILE                   => self::TYPE_MOBILE,
      PhoneNumberType::FIXED_LINE               => self::TYPE_FIXED_LINE,
      PhoneNumberType::FIXED_LINE_OR_MOBILE     => self::TYPE_FIXED_OR_MOBILE,
      PhoneNumberType::TOLL_FREE                => self::TYPE_TOLL_FREE,
      PhoneNumberType::PREMIUM_RATE             => self::TYPE_PREMIUM_RATE,
      PhoneNumberType::SHARED_COST              => self::TYPE_SHARED_COST,
      PhoneNumberType::VOIP                     => self::TYPE_VOIP,
      PhoneNumberType::PERSONAL_NUMBER          => self::TYPE_PERSONAL_NUMBER,
      PhoneNumberType::PAGER                    => self::TYPE_PAGER,
      PhoneNumberType::UAN                      => self::TYPE_UAN,
      PhoneNumberType::VOICEMAIL                => self::TYPE_VOICEMAIL,
      default                                   => self::TYPE_UNKNOWN,
    };
  }

  /**
   * Carrier name (e.g. "MTN", "Verizon"). Only reliable for mobile numbers.
   *
   * @return string|null Carrier name, or null if not derivable (returns null
   *                     rather than '' to match the package's return-null style).
   */
  public static function carrier(string $phone, string $locale = 'en', ?string $region = null):?string {
    $parsed = self::_parse($phone, $region);
    if ($parsed === null) {
      return null;
    }

    $mapper = PhoneNumberToCarrierMapper::getInstance();
    $name = $mapper->getNameForNumber($parsed, $locale);
    return !empty($name) ? $name : null;
  }

  /**
   * Rough geographic description of the number (e.g. "Lagos").
   * Granularity depends on the locale data; often falls back to the country.
   */
  public static function location(string $phone, string $locale = 'en', ?string $region = null):?string {
    $parsed = self::_parse($phone, $region);
    if ($parsed === null) {
      return null;
    }

    $geo = PhoneNumberOfflineGeocoder::getInstance();
    $desc = $geo->getDescriptionForNumber($parsed, $locale);
    return !empty($desc) ? $desc : null;
  }

  /**
   * Time zones associated with the phone number.
   *
   * @return array<string>|null List of IANA time zone ids, or null on failure.
   *                            Returns empty array if lib returns the "unknown" sentinel.
   */
  public static function timeZones(string $phone, ?string $region = null):?array {
    $parsed = self::_parse($phone, $region);
    if ($parsed === null) {
      return null;
    }

    $mapper = PhoneNumberToTimeZonesMapper::getInstance();
    $zones = $mapper->getTimeZonesForNumber($parsed);

    // libphonenumber returns ['Etc/Unknown'] when nothing matches.
    if (\count($zones) === 1 && $zones[0] === $mapper->getUnknownTimeZone()) {
      return [];
    }
    return $zones;
  }

  // =========================================================================
  // Validation
  // =========================================================================

  /**
   * Strict validation: is this a real, fully valid phone number?
   */
  public static function isValid(string $phone, ?string $region = null):bool {
    $parsed = self::_parse($phone, $region);
    if ($parsed === null) {
      return false;
    }

    return PhoneNumberUtil::getInstance()->isValidNumber($parsed);
  }

  /**
   * Loose validation: could this plausibly be a phone number? (length-based).
   * Use for quick filtering before expensive checks.
   */
  public static function isPossible(string $phone, ?string $region = null):bool {
    $parsed = self::_parse($phone, $region);
    if ($parsed === null) {
      return false;
    }

    return PhoneNumberUtil::getInstance()->isPossibleNumber($parsed);
  }

  /**
   * Check whether a number belongs to a specific region (ISO alpha-2).
   */
  public static function isRegion(string $phone, string $region):bool {
    $country = self::country($phone, $region);
    if ($country === null) {
      return false;
    }

    return \strtoupper($country) === \strtoupper($region);
  }

  // =========================================================================
  // Parsing & normalization
  // =========================================================================

  /**
   * Full structured breakdown of a phone number.
   *
   * @return array|null [
   *     'valid'       => bool,
   *     'possible'    => bool,
   *     'country'     => string|null,   // ISO alpha-2
   *     'country_name'=> string|null,
   *     'continent'   => string|null,
   *     'dial_code'   => int|null,
   *     'national'    => string|null,   // national-format string
   *     'e164'        => string|null,
   *     'intl'        => string|null,
   *     'rfc3966'     => string|null,
   *     'type'        => string|null,   // TYPE_* constant
   *     'carrier'     => string|null,
   *     'location'    => string|null,
   *     'time_zones'  => array,
   *   ]
   *   Returns null only if the number could not be parsed at all.
   */
  public static function parse(string $phone, ?string $region = null, string $locale = 'en'):?array {
    $parsed = self::_parse($phone, $region);
    if ($parsed === null) {
      return null;
    }

    $util = PhoneNumberUtil::getInstance();
    $iso  = $util->getRegionCodeForNumber($parsed);
    $iso  = ($iso && $iso !== 'ZZ') ? $iso : null;

    return [
      'valid'        => $util->isValidNumber($parsed),
      'possible'     => $util->isPossibleNumber($parsed),
      'country'      => $iso,
      'country_name' => $iso !== null ? self::countryName($phone, $locale, $region) : null,
      'continent'    => $iso !== null ? (self::CONTINENT_MAP[$iso] ?? null) : null,
      'dial_code'    => $parsed->getCountryCode() !== null ? (int)$parsed->getCountryCode() : null,
      'national'     => $util->format($parsed, PhoneNumberFormat::NATIONAL),
      'e164'         => $util->format($parsed, PhoneNumberFormat::E164),
      'intl'         => $util->format($parsed, PhoneNumberFormat::INTERNATIONAL),
      'rfc3966'      => $util->format($parsed, PhoneNumberFormat::RFC3966),
      'type'         => self::type($phone, $region),
      'carrier'      => self::carrier($phone, $locale, $region),
      'location'     => self::location($phone, $locale, $region),
      'time_zones'   => self::timeZones($phone, $region) ?? [],
    ];
  }

  /**
   * Strip all non-digit characters except a leading "+" sign.
   * Cheap, no libphonenumber required — useful as a pre-clean before storage.
   */
  public static function normalize(string $phone):string {
    $phone = \trim($phone);
    $hasPlus = \str_starts_with($phone, '+');
    $digits = \preg_replace('/\D+/', '', $phone) ?? '';
    return ($hasPlus ? '+' : '') . $digits;
  }

  // =========================================================================
  // Internal
  // =========================================================================

  /**
   * Safe wrapper around PhoneNumberUtil::parse() — swallows NumberParseException.
   */
  private static function _parse(string $phone, ?string $region = null):?\libphonenumber\PhoneNumber {
    $phone = \trim($phone);
    if ($phone === '') {
      return null;
    }

    $util = PhoneNumberUtil::getInstance();
    $reg  = $region !== null ? \strtoupper($region) : null;

    try {
      return $util->parse($phone, $reg);
    } catch (NumberParseException $e) {
      return null;
    }
  }
}
