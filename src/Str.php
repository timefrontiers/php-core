<?php

declare(strict_types=1);

namespace TimeFrontiers;

/**
 * String utility functions.
 */
class Str {

  /**
   * Parse "Name <email>" format into components.
   *
   * @param string $string Input like "John Doe <john@example.com>"
   * @return array ['name' => '', 'surname' => '', 'email' => '']
   */
  public static function parseEmailName(string $string):array {
    $result = [
      'name' => '',
      'surname' => '',
      'email' => '',
    ];

    $parts = \explode('<', $string);

    if (\count($parts) === 2) {
      // Format: "Name <email>"
      $name = \filter_var(\trim($parts[0]), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
      $email = \filter_var(
        \trim(\str_replace(['<', '>'], '', $parts[1])),
        FILTER_SANITIZE_EMAIL
      );

      if (!empty($name)) {
        $name_parts = \preg_split('/\s+/', $name, 2);
        $result['name'] = \str_replace(' ', '', \ucwords($name_parts[0]));

        if (\count($name_parts) > 1) {
          $result['surname'] = \str_replace(' ', '', \ucwords($name_parts[1]));
        }
      }

      $result['email'] = \filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    } else {
      // Just email
      $email = \filter_var($string, FILTER_SANITIZE_EMAIL);
      $result['email'] = \filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    return $result;
  }

  /**
   * Get file extension from a filename or URL.
   *
   * @param string $filename The filename or URL
   * @return string File extension (without dot)
   */
  public static function fileExtension(string $filename):string {
    // Remove query string
    $qpos = \strpos($filename, '?');
    if ($qpos !== false) {
      $filename = \substr($filename, 0, $qpos);
    }

    return \pathinfo($filename, PATHINFO_EXTENSION);
  }

  /**
   * Pattern-based string replacement.
   *
   * @param array $patterns [key => pattern] pairs
   * @param array $replacements [key => replacement] pairs
   * @param string $value String to process
   * @return string Processed string
   */
  public static function patternReplace(array $patterns, array $replacements, string $value):string {
    foreach ($patterns as $key => $pattern) {
      if (\array_key_exists($key, $replacements)) {
        $value = \str_replace($pattern, $replacements[$key], $value);
      }
    }

    return $value;
  }

  /**
   * Check if a string is valid Base64.
   *
   * @param string $data The string to check
   * @return bool True if valid Base64
   */
  public static function isBase64(string $data):bool {
    return (bool) \preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $data);
  }

  /**
   * Safely decode Base64.
   *
   * @param string $data Base64 encoded string
   * @return string|false Decoded string or false on failure
   */
  public static function base64Decode(string $data):string|false {
    if (!self::isBase64($data)) {
      return false;
    }

    return \base64_decode($data, true);
  }

  /**
   * Generate a URL-safe slug.
   *
   * @param string $text Text to slugify
   * @param string $separator Word separator (default: -)
   * @return string URL-safe slug
   */
  public static function slug(string $text, string $separator = '-'):string {
    // Convert to lowercase
    $text = \strtolower($text);

    // Replace non-alphanumeric characters with separator
    $text = \preg_replace('/[^a-z0-9]+/', $separator, $text);

    // Remove leading/trailing separators
    return \trim($text, $separator);
  }

  /**
   * Truncate string to a maximum length.
   *
   * @param string $text Text to truncate
   * @param int $length Maximum length
   * @param string $suffix Suffix to append if truncated
   * @return string Truncated string
   */
  public static function truncate(string $text, int $length, string $suffix = '...'):string {
    if (\mb_strlen($text) <= $length) {
      return $text;
    }

    return \mb_substr($text, 0, $length - \mb_strlen($suffix)) . $suffix;
  }

  /**
   * Truncate to word boundary.
   *
   * @param string $text Text to truncate
   * @param int $length Maximum length
   * @param string $suffix Suffix to append if truncated
   * @return string Truncated string
   */
  public static function truncateWords(string $text, int $length, string $suffix = '...'):string {
    if (\mb_strlen($text) <= $length) {
      return $text;
    }

    $truncated = \mb_substr($text, 0, $length);
    $last_space = \mb_strrpos($truncated, ' ');

    if ($last_space !== false) {
      $truncated = \mb_substr($truncated, 0, $last_space);
    }

    return \rtrim($truncated, '.,!? ') . $suffix;
  }

  /**
   * Limit string to a number of words.
   *
   * @param string $text Text to limit
   * @param int $words Maximum number of words
   * @param string $suffix Suffix to append if limited
   * @return string Limited string
   */
  public static function limitWords(string $text, int $words, string $suffix = '...'):string {
    $arr = \preg_split('/\s+/', $text, $words + 1);

    if (\count($arr) <= $words) {
      return $text;
    }

    \array_pop($arr);
    return \implode(' ', $arr) . $suffix;
  }

  /**
   * Convert string to camelCase.
   */
  public static function toCamelCase(string $text):string {
    $text = \str_replace(['-', '_'], ' ', $text);
    $text = \ucwords($text);
    $text = \str_replace(' ', '', $text);

    return \lcfirst($text);
  }

  /**
   * Convert string to PascalCase.
   */
  public static function toPascalCase(string $text):string {
    $text = \str_replace(['-', '_'], ' ', $text);
    $text = \ucwords($text);

    return \str_replace(' ', '', $text);
  }

  /**
   * Convert string to snake_case.
   */
  public static function toSnakeCase(string $text):string {
    // Add underscore before capitals
    $text = \preg_replace('/([a-z])([A-Z])/', '$1_$2', $text);

    // Replace spaces and dashes
    $text = \str_replace([' ', '-'], '_', $text);

    return \strtolower($text);
  }

  /**
   * Convert string to kebab-case.
   */
  public static function toKebabCase(string $text):string {
    // Add dash before capitals
    $text = \preg_replace('/([a-z])([A-Z])/', '$1-$2', $text);

    // Replace spaces and underscores
    $text = \str_replace([' ', '_'], '-', $text);

    return \strtolower($text);
  }

  /**
   * Mask a string (e.g., for credit cards, emails).
   *
   * @param string $text String to mask
   * @param int $visible_start Characters visible at start
   * @param int $visible_end Characters visible at end
   * @param string $mask_char Masking character
   * @return string Masked string
   */
  public static function mask(
    string $text,
    int $visible_start = 4,
    int $visible_end = 4,
    string $mask_char = '*'
  ):string {
    $len = \strlen($text);

    if ($len <= $visible_start + $visible_end) {
      return $text;
    }

    $start = \substr($text, 0, $visible_start);
    $end = \substr($text, -$visible_end);
    $mask = \str_repeat($mask_char, $len - $visible_start - $visible_end);

    return $start . $mask . $end;
  }

  /**
   * Check if string contains another string.
   */
  public static function contains(string $haystack, string $needle, bool $case_sensitive = true):bool {
    if (!$case_sensitive) {
      return \str_contains(\strtolower($haystack), \strtolower($needle));
    }

    return \str_contains($haystack, $needle);
  }

  /**
   * Check if string starts with another string.
   */
  public static function startsWith(string $haystack, string $needle, bool $case_sensitive = true):bool {
    if (!$case_sensitive) {
      return \str_starts_with(\strtolower($haystack), \strtolower($needle));
    }

    return \str_starts_with($haystack, $needle);
  }

  /**
   * Check if string ends with another string.
   */
  public static function endsWith(string $haystack, string $needle, bool $case_sensitive = true):bool {
    if (!$case_sensitive) {
      return \str_ends_with(\strtolower($haystack), \strtolower($needle));
    }

    return \str_ends_with($haystack, $needle);
  }

  /**
   * Count words in a string.
   */
  public static function wordCount(string $text):int {
    return \str_word_count($text);
  }

  /**
   * Generate an excerpt from text: strip HTML, normalize whitespace, truncate
   * at a word boundary.
   *
   * @param string $text Full text (may contain HTML)
   * @param int $length Maximum excerpt length
   * @param string $suffix Suffix when truncated
   * @return string Plain-text excerpt
   */
  public static function excerpt(string $text, int $length = 200, string $suffix = '...'):string {
    $text = \strip_tags($text);
    $text = \preg_replace('/\s+/', ' ', $text);
    $text = \trim($text);

    return self::truncateWords($text, $length, $suffix);
  }

  /**
   * Extract an excerpt window around a search phrase (with ellipses on either
   * side when the excerpt is a middle slice of the source).
   *
   * @param string $text Full text
   * @param string $phrase Phrase to find
   * @param int $radius Characters around phrase
   * @param string $ellipsis Ellipsis string
   * @return string Excerpt
   */
  public static function excerptAround(
    string $text,
    string $phrase,
    int $radius = 100,
    string $ellipsis = '...'
  ):string {
    $pos = \mb_stripos($text, $phrase);

    if ($pos === false) {
      return self::truncate($text, $radius * 2, $ellipsis);
    }

    $start = \max(0, $pos - $radius);
    $end = \min(\mb_strlen($text), $pos + \mb_strlen($phrase) + $radius);

    $excerpt = \mb_substr($text, $start, $end - $start);

    if ($start > 0) {
      $excerpt = $ellipsis . \ltrim($excerpt);
    }

    if ($end < \mb_strlen($text)) {
      $excerpt = \rtrim($excerpt) . $ellipsis;
    }

    return $excerpt;
  }

  /**
   * Split a string into fixed-size chunks joined by a separator
   * (e.g., `"1234567890"` → `"123-456-789-0"`).
   */
  public static function chunk(string $string, int $chunk_size = 3, string $separator = '-'):string {
    if ($chunk_size < 1) {
      return $string;
    }
    return \implode($separator, \str_split($string, $chunk_size));
  }
}
