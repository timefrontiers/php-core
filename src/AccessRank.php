<?php

declare(strict_types=1);

namespace TimeFrontiers;

/**
 * User access rank levels.
 *
 * Used for filtering error visibility, access control,
 * and permission management across TimeFrontiers packages.
 *
 * Higher rank = more privileges and can see more detailed errors.
 */
enum AccessRank: int {

  case GUEST = 0;
  case USER = 1;
  case ANALYST = 2;
  case ADVERTISER = 3;
  case MODERATOR = 4;
  case EDITOR = 5;
  case ADMIN = 6;
  case DEVELOPER = 7;
  case SUPERADMIN = 8;
  case OWNER = 14;

  /**
   * Check if this rank can see errors for a given minimum rank.
   */
  public function canSee(int $min_rank):bool {
    return $this->value >= $min_rank;
  }

  /**
   * Check if this rank meets or exceeds another rank.
   */
  public function atLeast(AccessRank $rank):bool {
    return $this->value >= $rank->value;
  }

  /**
   * Get a user-friendly label.
   */
  public function label():string {
    return match ($this) {
      self::GUEST => 'Guest',
      self::USER => 'User',
      self::ANALYST => 'Analyst',
      self::ADVERTISER => 'Advertiser',
      self::MODERATOR => 'Moderator',
      self::EDITOR => 'Editor',
      self::ADMIN => 'Administrator',
      self::DEVELOPER => 'Developer',
      self::SUPERADMIN => 'Super Administrator',
      self::OWNER => 'Owner',
    };
  }

  /**
   * Check if this is a staff-level rank (can see internal errors).
   */
  public function isStaff():bool {
    return $this->value >= self::MODERATOR->value;
  }

  /**
   * Check if this is a technical rank (can see debug info).
   */
  public function isTechnical():bool {
    return $this->value >= self::DEVELOPER->value;
  }

  /**
   * Check if this is an admin rank.
   */
  public function isAdmin():bool {
    return $this->value >= self::ADMIN->value;
  }

  /**
   * Get rank from integer value.
   */
  public static function fromValue(int $value):?self {
    foreach (self::cases() as $case) {
      if ($case->value === $value) {
        return $case;
      }
    }
    return null;
  }

  /**
   * Get all ranks as [value => label] array.
   */
  public static function options():array {
    $options = [];
    foreach (self::cases() as $case) {
      $options[$case->value] = $case->label();
    }
    return $options;
  }
}
