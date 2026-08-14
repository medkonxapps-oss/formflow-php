<?php

declare(strict_types=1);

namespace FormFlow;

use PDO;

/**
 * Login activity for admin security settings.
 */
class LoginActivityRepository
{
  private Database $db;

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(private array $config)
  {
    $this->db = Database::getInstance($config);
  }

  /**
   * @return list<array<string, mixed>>
   */
  public function recent(int $limit = 50): array
  {
    $limit = max(1, min(200, $limit));
    $tblAttempts = Db::table('login_attempts', $this->config);
    $tblAudit = Db::table('audit_log', $this->config);
    $tblUsers = Db::table('users', $this->config);

    $attempts = $this->db->query(
      "SELECT id, identifier, identifier_type, success, ip_address, user_agent, created_at
       FROM {$tblAttempts}
       ORDER BY created_at DESC
       LIMIT {$limit}"
    )->fetchAll(PDO::FETCH_ASSOC);

    $audit = $this->db->query(
      "SELECT al.id, al.action, al.ip_address, al.created_at, u.name AS user_name, u.email AS user_email
       FROM {$tblAudit} al
       LEFT JOIN {$tblUsers} u ON u.id = al.user_id
       WHERE al.action IN ('login.success', 'login.failed', 'login.lockout', 'logout')
       ORDER BY al.created_at DESC
       LIMIT {$limit}"
    )->fetchAll(PDO::FETCH_ASSOC);

    $rows = [];
    if (is_array($attempts)) {
      foreach ($attempts as $row) {
        $rows[] = [
          'type' => 'attempt',
          'identifier' => (string) ($row['identifier'] ?? ''),
          'identifier_type' => (string) ($row['identifier_type'] ?? ''),
          'success' => (int) ($row['success'] ?? 0),
          'ip_address' => (string) ($row['ip_address'] ?? ''),
          'user_agent' => (string) ($row['user_agent'] ?? ''),
          'created_at' => (string) ($row['created_at'] ?? ''),
        ];
      }
    }

    if (is_array($audit)) {
      foreach ($audit as $row) {
        $rows[] = [
          'type' => 'audit',
          'action' => (string) ($row['action'] ?? ''),
          'user_name' => (string) ($row['user_name'] ?? ''),
          'user_email' => (string) ($row['user_email'] ?? ''),
          'ip_address' => (string) ($row['ip_address'] ?? ''),
          'created_at' => (string) ($row['created_at'] ?? ''),
        ];
      }
    }

    usort($rows, fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

    return array_slice($rows, 0, $limit);
  }
}
