<?php

declare(strict_types=1);

namespace FormFlow;

use PDO;

/**
 * Dashboard and cross-form submission statistics (indexed queries).
 */
class StatsRepository
{
  private Database $db;

  private string $tblForms;
  private string $tblSubmissions;

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(private array $config)
  {
    $this->db = Database::getInstance($config);
    $this->tblForms = Db::table('forms', $config);
    $this->tblSubmissions = Db::table('submissions', $config);
  }

  /**
   * @return array{total_forms: int, total_submissions: int, submissions_this_week: int, spam_caught: int}
   */
  public function dashboardSummary(int $userId): array
  {
    $forms = $this->db->fetchOne(
      "SELECT COUNT(*) AS cnt FROM {$this->tblForms} WHERE user_id = ?",
      [$userId]
    );

    $subs = $this->db->fetchOne(
      "SELECT COUNT(*) AS cnt FROM {$this->tblSubmissions} s
       INNER JOIN {$this->tblForms} f ON f.id = s.form_id
       WHERE f.user_id = ? AND s.is_spam = 0",
      [$userId]
    );

    $week = $this->db->fetchOne(
      "SELECT COUNT(*) AS cnt FROM {$this->tblSubmissions} s
       INNER JOIN {$this->tblForms} f ON f.id = s.form_id
       WHERE f.user_id = ? AND s.is_spam = 0 AND s.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)",
      [$userId]
    );

    $spam = $this->db->fetchOne(
      "SELECT COUNT(*) AS cnt FROM {$this->tblSubmissions} s
       INNER JOIN {$this->tblForms} f ON f.id = s.form_id
       WHERE f.user_id = ? AND s.is_spam = 1",
      [$userId]
    );

    return [
      'total_forms' => (int) ($forms['cnt'] ?? 0),
      'total_submissions' => (int) ($subs['cnt'] ?? 0),
      'submissions_this_week' => (int) ($week['cnt'] ?? 0),
      'spam_caught' => (int) ($spam['cnt'] ?? 0),
    ];
  }

  /**
   * @return list<array<string, mixed>>
   */
  public function recentSubmissions(int $userId, int $limit = 10): array
  {
    $limit = max(1, min(50, $limit));
    $stmt = $this->db->query(
      "SELECT s.id, s.form_id, s.data_json, s.is_spam, s.is_read, s.created_at, f.name AS form_name, f.slug AS form_slug
       FROM {$this->tblSubmissions} s
       INNER JOIN {$this->tblForms} f ON f.id = s.form_id
       WHERE f.user_id = ?
       ORDER BY s.created_at DESC
       LIMIT {$limit}",
      [$userId]
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($rows)) {
      return [];
    }

    return array_map(function (array $row): array {
      $row['data'] = JsonColumn::decode($row['data_json'] ?? null);
      unset($row['data_json']);

      return $row;
    }, $rows);
  }

  /**
   * Daily submission counts for the last N days (non-spam).
   *
   * @return list<array{date: string, count: int}>
   */
  public function submissionsChart(int $userId, int $days = 30): array
  {
    $days = max(1, min(90, $days));
    $stmt = $this->db->query(
      "SELECT DATE(s.created_at) AS day, COUNT(*) AS cnt
       FROM {$this->tblSubmissions} s
       INNER JOIN {$this->tblForms} f ON f.id = s.form_id
       WHERE f.user_id = ? AND s.is_spam = 0 AND s.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)
       GROUP BY DATE(s.created_at)
       ORDER BY day ASC",
      [$userId, $days]
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    if (is_array($rows)) {
      foreach ($rows as $row) {
        $map[(string) $row['day']] = (int) $row['cnt'];
      }
    }

    $result = [];
    for ($i = $days - 1; $i >= 0; $i--) {
      $date = gmdate('Y-m-d', strtotime("-{$i} days"));
      $result[] = ['date' => $date, 'count' => $map[$date] ?? 0];
    }

    return $result;
  }
}
