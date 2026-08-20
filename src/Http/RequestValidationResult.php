<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

/** @phpstan-type ErrorMap array<string, list<string>> */
final readonly class RequestValidationResult {

  /**
   * @param array<string, mixed> $validated
   * @param ErrorMap $errors
   */
  public function __construct(
    private bool $valid,
    private array $validated,
    private array $errors
  ) {}

  public function passes():bool {
    return $this->valid;
  }

  public function fails():bool {
    return !$this->valid;
  }

  /** @return array<string, mixed> */
  public function validated():array {
    return $this->validated;
  }

  /** @return ErrorMap */
  public function errors():array {
    return $this->errors;
  }

  /** @return list<string> */
  public function messages():array {
    $messages = [];
    foreach ($this->errors as $fieldErrors) {
      foreach ($fieldErrors as $message) {
        $messages[] = $message;
      }
    }
    return $messages;
  }
}
