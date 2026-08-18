<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * JSON encode/decode helpers for LONGTEXT columns (MySQL/MariaDB compatible).
 */
class JsonColumn
{
  /**
   * @return array<int|string, mixed>
   */
  public static function decode(?string $json): array
  {
    if ($json === null || $json === '') {
      return [];
    }

    $data = json_decode($json, true);

    return is_array($data) ? $data : [];
  }

  /**
   * @param array<int|string, mixed> $data
   */
  public static function encode(array $data): string
  {
    $json = json_encode($data);
    if (!is_string($json) || $json === '') {
      return '{}';
    }

    return $json;
  }
}
