<?php

declare(strict_types=1);

namespace FormFlow;

use PDO;

/**
 * Per-form analytics aggregations.
 */
class AnalyticsRepository
{
  private Database $db;

  private string $tblSubmissions;

  private FormRepository $forms;

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(private array $config)
  {
    $this->db = Database::getInstance($config);
    $this->tblSubmissions = Db::table('submissions', $config);
    $this->forms = new FormRepository($config);
  }

  /**
   * @return list<array{period: string, count: int}>
   */
  public function submissionsOverTime(int $formId, int $userId, string $granularity = 'daily'): array
  {
    if ($this->forms->findForUser($formId, $userId) === null) {
      return [];
    }

    $format = match ($granularity) {
      'weekly' => '%x-W%v',
      'monthly' => '%Y-%m',
      default => '%Y-%m-%d',
    };

    $stmt = $this->db->query(
      "SELECT DATE_FORMAT(s.created_at, '{$format}') AS period, COUNT(*) AS cnt
       FROM {$this->tblSubmissions} s
       WHERE s.form_id = ? AND s.is_spam = 0 AND s.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY)
       GROUP BY period
       ORDER BY period ASC",
      [$formId]
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows)
      ? array_map(fn (array $r) => ['period' => (string) $r['period'], 'count' => (int) $r['cnt']], $rows)
      : [];
  }

  /**
   * @return list<array{domain: string, count: int}>
   */
  public function topReferrers(int $formId, int $userId, int $limit = 10): array
  {
    if ($this->forms->findForUser($formId, $userId) === null) {
      return [];
    }

    $limit = max(1, min(25, $limit));
    $stmt = $this->db->query(
      "SELECT
         LOWER(
           SUBSTRING_INDEX(
             SUBSTRING_INDEX(REPLACE(REPLACE(REPLACE(s.referrer, 'https://', ''), 'http://', ''), 'www.', ''), '/', 1),
             ':', 1
           )
         ) AS domain,
         COUNT(*) AS cnt
       FROM {$this->tblSubmissions} s
       WHERE s.form_id = ? AND s.referrer IS NOT NULL AND s.referrer != '' AND s.is_spam = 0
       GROUP BY domain
       ORDER BY cnt DESC
       LIMIT {$limit}",
      [$formId]
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows)
      ? array_map(fn (array $r) => ['domain' => (string) $r['domain'], 'count' => (int) $r['cnt']], $rows)
      : [];
  }

  /**
   * @return array{total_submissions: int, spam: int}
   */
  public function formTotals(int $formId, int $userId): array
  {
    if ($this->forms->findForUser($formId, $userId) === null) {
      return ['total_submissions' => 0, 'spam' => 0];
    }

    $row = $this->db->fetchOne(
      "SELECT
         SUM(CASE WHEN is_spam = 0 THEN 1 ELSE 0 END) AS legit,
         SUM(CASE WHEN is_spam = 1 THEN 1 ELSE 0 END) AS spam
       FROM {$this->tblSubmissions} WHERE form_id = ?",
      [$formId]
    );

    return [
      'total_submissions' => (int) ($row['legit'] ?? 0),
      'spam' => (int) ($row['spam'] ?? 0),
    ];
  }
}
