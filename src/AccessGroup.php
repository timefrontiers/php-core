<?php

declare(strict_types=1);

namespace TimeFrontiers;

/**
 * User access group (string-backed).
 *
 * Companion to AccessRank for string-based group identification.
 * Use AccessRank for numeric comparisons, AccessGroup for string storage.
 */
enum AccessGroup: string {

  case GUEST = 'GUEST';
  case USER = 'USER';
  case ANALYST = 'ANALYST';
  case ADVERTISER = 'ADVERTISER';
  case MODERATOR = 'MODERATOR';
  case EDITOR = 'EDITOR';
  case ADMIN = 'ADMIN';
  case DEVELOPER = 'DEVELOPER';
  case SUPERADMIN = 'SUPERADMIN';
  case OWNER = 'OWNER';

  /**
   * Get the corresponding AccessRank.
   */
  public function toRank():AccessRank {
    return match ($this) {
      self::GUEST => AccessRank::GUEST,
      self::USER => AccessRank::USER,
      self::ANALYST => AccessRank::ANALYST,
      self::ADVERTISER => AccessRank::ADVERTISER,
      self::MODERATOR => AccessRank::MODERATOR,
      self::EDITOR => AccessRank::EDITOR,
      self::ADMIN => AccessRank::ADMIN,
      self::DEVELOPER => AccessRank::DEVELOPER,
      self::SUPERADMIN => AccessRank::SUPERADMIN,
      self::OWNER => AccessRank::OWNER,
    };
  }

  /**
   * Get numeric rank value.
   */
  public function rankValue():int {
    return $this->toRank()->value;
  }

  /**
   * Get a user-friendly label.
   */
  public function label():string {
    return $this->toRank()->label();
  }

  /**
   * Check if this group is staff level or higher.
   */
  public function isStaff():bool {
    return $this->toRank()->isStaff();
  }

  /**
   * Check if this group is technical level or higher.
   */
  public function isTechnical():bool {
    return $this->toRank()->isTechnical();
  }

  /**
   * Check if this group is admin level or higher.
   */
  public function isAdmin():bool {
    return $this->toRank()->isAdmin();
  }

  /**
   * Check if this group meets or exceeds another group.
   */
  public function atLeast(AccessGroup $group):bool {
    return $this->rankValue() >= $group->rankValue();
  }

  /**
   * Create from AccessRank.
   */
  public static function fromRank(AccessRank $rank):self {
    return match ($rank) {
      AccessRank::GUEST => self::GUEST,
      AccessRank::USER => self::USER,
      AccessRank::ANALYST => self::ANALYST,
      AccessRank::ADVERTISER => self::ADVERTISER,
      AccessRank::MODERATOR => self::MODERATOR,
      AccessRank::EDITOR => self::EDITOR,
      AccessRank::ADMIN => self::ADMIN,
      AccessRank::DEVELOPER => self::DEVELOPER,
      AccessRank::SUPERADMIN => self::SUPERADMIN,
      AccessRank::OWNER => self::OWNER,
    };
  }

  /**
   * Get all groups as [value => label] array.
   */
  public static function options():array {
    $options = [];
    foreach (self::cases() as $case) {
      $options[$case->value] = $case->label();
    }
    return $options;
  }
}
