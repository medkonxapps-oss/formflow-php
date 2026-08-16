<?php

declare(strict_types=1);

namespace FormFlow;

use PDO;

/**
 * Team invite tokens (admin-only create).
 */
class InviteRepository
{
  private Database $db;

  private string $tbl;

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(private array $config)
  {
    $this->db = Database::getInstance($config);
    $this->tbl = Db::table('invites', $config);
  }

  /**
   * @return list<array<string, mixed>>
   */
  public function pending(): array
  {
    $stmt = $this->db->query(
      "SELECT id, email, role, expires_at, accepted_at, created_at
       FROM {$this->tbl}
       ORDER BY created_at DESC
       LIMIT 50"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
  }

  /**
   * @return array{success: bool, token?: string, error?: string}
   */
  public function create(int $invitedBy, string $email, string $role): array
  {
    $email = strtolower(trim($email));
    $role = in_array($role, ['admin', 'editor', 'viewer'], true) ? $role : 'editor';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return ['success' => false, 'error' => 'Enter a valid email address.'];
    }

    $existing = $this->db->fetchOne(
      'SELECT id FROM ' . Db::table('users', $this->config) . ' WHERE email = ? LIMIT 1',
      [$email]
    );
    if ($existing !== null) {
      return ['success' => false, 'error' => 'That email already has an account.'];
    }

    $token = bin2hex(random_bytes(24));
    $hash = hash('sha256', $token);

    $this->db->query(
      "INSERT INTO {$this->tbl} (email, role, token_hash, invited_by_user_id, expires_at, created_at)
       VALUES (?, ?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY), UTC_TIMESTAMP())",
      [$email, $role, $hash, $invitedBy]
    );

    return ['success' => true, 'token' => $token];
  }

  /**
   * @return array<string, mixed>|null
   */
  public function findValidByToken(string $token): ?array
  {
    $token = trim($token);
    if ($token === '') {
      return null;
    }

    $row = $this->db->fetchOne(
      "SELECT * FROM {$this->tbl}
       WHERE token_hash = ? AND accepted_at IS NULL AND expires_at > UTC_TIMESTAMP()
       LIMIT 1",
      [hash('sha256', $token)]
    );

    return $row;
  }

  public function markAccepted(int $inviteId): void
  {
    $this->db->query(
      "UPDATE {$this->tbl} SET accepted_at = UTC_TIMESTAMP() WHERE id = ?",
      [$inviteId]
    );
  }
}
