<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Records public form views for conversion analytics.
 */
class ViewTracker
{
  private Database $db;

  private string $tbl;

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(private array $config)
  {
    $this->db = Database::getInstance($config);
    $this->tbl = Db::table('form_views', $config);
  }

  public function record(int $formId, string $ip, string $referrer): void
  {
    $ipHash = hash('sha256', $ip . ((string) ($this->config['app']['secret'] ?? '')));
    $referrer = substr($referrer, 0, 2048);

    $recent = $this->db->fetchOne(
      "SELECT id FROM {$this->tbl}
       WHERE form_id = ? AND ip_hash = ? AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 MINUTE)
       LIMIT 1",
      [$formId, $ipHash]
    );

    if ($recent !== null) {
      return;
    }

    $this->db->query(
      "INSERT INTO {$this->tbl} (form_id, ip_hash, referrer, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())",
      [$formId, $ipHash, $referrer !== '' ? $referrer : null]
    );
  }

  public function countForForm(int $formId, int $days = 90): int
  {
    $row = $this->db->fetchOne(
      "SELECT COUNT(*) AS cnt FROM {$this->tbl}
       WHERE form_id = ? AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)",
      [$formId, $days]
    );

    return (int) ($row['cnt'] ?? 0);
  }

  /**
   * @return list<array{period: string, count: int}>
   */
  public function overTime(int $formId, int $days = 30): array
  {
    $stmt = $this->db->query(
      "SELECT DATE(created_at) AS period, COUNT(*) AS cnt
       FROM {$this->tbl}
       WHERE form_id = ? AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)
       GROUP BY DATE(created_at)
       ORDER BY period ASC",
      [$formId, $days]
    );

    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    if (!is_array($rows)) {
      return [];
    }

    return array_map(static fn (array $r): array => [
      'period' => (string) $r['period'],
      'count' => (int) $r['cnt'],
    ], $rows);
  }
}
