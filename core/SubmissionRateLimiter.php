<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Per-IP and per-form submission rate limiting.
 */
class SubmissionRateLimiter
{
  /**
   * @param array<string, mixed> $config
   */
  public function __construct(private array $config)
  {
  }

  public function isLimited(int $formId, string $ip, int $perMinute): bool
  {
    if ($perMinute <= 0) {
      return false;
    }

    $tbl = Db::table('submission_rate_log', $this->config);
    $db = Database::getInstance($this->config);
    $since = gmdate('Y-m-d H:i:s', time() - 60);

    $ipCount = $db->fetchOne(
      "SELECT COUNT(*) AS cnt FROM {$tbl} WHERE ip_address = ? AND created_at >= ?",
      [$ip, $since]
    );

    if ((int) ($ipCount['cnt'] ?? 0) >= $perMinute) {
      return true;
    }

    $formIpCount = $db->fetchOne(
      "SELECT COUNT(*) AS cnt FROM {$tbl} WHERE form_id = ? AND ip_address = ? AND created_at >= ?",
      [$formId, $ip, $since]
    );

    return (int) ($formIpCount['cnt'] ?? 0) >= $perMinute;
  }

  public function record(int $formId, string $ip): void
  {
    $tbl = Db::table('submission_rate_log', $this->config);
    $db = Database::getInstance($this->config);

    $db->query(
      "INSERT INTO {$tbl} (form_id, ip_address, created_at) VALUES (?, ?, UTC_TIMESTAMP())",
      [$formId, $ip]
    );
  }
}
