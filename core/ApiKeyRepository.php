<?php

declare(strict_types=1);

namespace FormFlow;

use PDO;

/**
 * API key management — store only hashes (PRD §5.8).
 */
class ApiKeyRepository
{
  private Database $db;

  private string $tbl;

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(private array $config)
  {
    $this->db = Database::getInstance($config);
    $this->tbl = Db::table('api_keys', $config);
  }

  /**
   * @return list<array<string, mixed>>
   */
  public function listForUser(int $userId): array
  {
    $stmt = $this->db->query(
      "SELECT id, name, key_prefix, last_used_at, created_at, revoked_at
       FROM {$this->tbl}
       WHERE user_id = ?
       ORDER BY created_at DESC",
      [$userId]
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
  }

  /**
   * @return array{success: bool, raw_key?: string, key_id?: int, error?: string}
   */
  public function generate(int $userId, string $name): array
  {
    $name = trim($name);
    if ($name === '') {
      return ['success' => false, 'error' => 'Key name is required.'];
    }

    $raw = 'ff_' . bin2hex(random_bytes(24));
    $hash = hash('sha256', $raw);
    $prefix = substr($raw, 0, 10);

    $this->db->query(
      "INSERT INTO {$this->tbl} (user_id, name, key_hash, key_prefix, created_at)
       VALUES (?, ?, ?, ?, UTC_TIMESTAMP())",
      [$userId, $name, $hash, $prefix]
    );

    return [
      'success' => true,
      'raw_key' => $raw,
      'key_id' => (int) $this->db->pdo()->lastInsertId(),
    ];
  }

  public function revoke(int $keyId, int $userId): bool
  {
    $stmt = $this->db->query(
      "UPDATE {$this->tbl} SET revoked_at = UTC_TIMESTAMP()
       WHERE id = ? AND user_id = ? AND revoked_at IS NULL",
      [$keyId, $userId]
    );

    return $stmt->rowCount() > 0;
  }

  /**
   * @return array<string, mixed>|null authenticated user row
   */
  public function authenticate(string $rawKey): ?array
  {
    $rawKey = trim($rawKey);
    if ($rawKey === '' || !str_starts_with($rawKey, 'ff_')) {
      return null;
    }

    $hash = hash('sha256', $rawKey);
    $row = $this->db->fetchOne(
      "SELECT k.id AS key_id, k.user_id, u.*
       FROM {$this->tbl} k
       INNER JOIN " . Db::table('users', $this->config) . " u ON u.id = k.user_id
       WHERE k.key_hash = ? AND k.revoked_at IS NULL
       LIMIT 1",
      [$hash]
    );

    if ($row === null) {
      return null;
    }

    $this->db->query(
      "UPDATE {$this->tbl} SET last_used_at = UTC_TIMESTAMP() WHERE id = ?",
      [(int) $row['key_id']]
    );

    return $row;
  }
}
