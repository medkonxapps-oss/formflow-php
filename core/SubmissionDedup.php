<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Duplicate submission detection (rolling window dedup).
 */
class SubmissionDedup
{
  private const WINDOW_SECONDS = 30;

  /** @var array<string, mixed> */
  private array $config;

  private string $tbl;

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(array $config)
  {
    $this->config = $config;
    $this->tbl = Db::table('submission_dedup', $config);
  }

  /**
   * @param array<string, mixed> $payload
   * @return array<string, mixed>|null cached success response
   */
  public function findDuplicate(int $formId, array $payload, string $ip): ?array
  {
    $hash = $this->hash($formId, $payload, $ip);
    $db = Database::getInstance($this->config);

    $row = $db->fetchOne(
      "SELECT response_json FROM {$this->tbl} WHERE dedup_hash = ? AND expires_at > UTC_TIMESTAMP() LIMIT 1",
      [$hash]
    );

    if ($row === null) {
      return null;
    }

    $decoded = json_decode((string) ($row['response_json'] ?? ''), true);

    return is_array($decoded) ? $decoded : null;
  }

  /**
   * @param array<string, mixed> $payload
   * @param array<string, mixed> $response
   */
  public function store(int $formId, array $payload, string $ip, int $submissionId, array $response): void
  {
    $hash = $this->hash($formId, $payload, $ip);
    $expires = gmdate('Y-m-d H:i:s', time() + self::WINDOW_SECONDS);
    $db = Database::getInstance($this->config);

    $db->query(
      "INSERT INTO {$this->tbl} (dedup_hash, submission_id, response_json, created_at, expires_at)
       VALUES (?, ?, ?, UTC_TIMESTAMP(), ?)
       ON DUPLICATE KEY UPDATE submission_id = VALUES(submission_id), response_json = VALUES(response_json), expires_at = VALUES(expires_at)",
      [$hash, $submissionId, json_encode($response, JSON_UNESCAPED_UNICODE), $expires]
    );
  }

  /**
   * @param array<string, mixed> $payload
   */
  private function hash(int $formId, array $payload, string $ip): string
  {
    ksort($payload);
    foreach ($payload as $key => $value) {
      if (is_array($value)) {
        $payload[$key] = array_values(array_map(
          static fn($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_SORT_KEYS) : $v,
          $value
        ));
        sort($payload[$key]);
      }
    }
    $normalized = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_SORT_KEYS);

    return hash('sha256', $formId . '|' . $normalized . '|' . $ip);
  }
}
