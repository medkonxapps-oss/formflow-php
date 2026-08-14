<?php

declare(strict_types=1);

namespace FormFlow;

use PDOException;

/**
 * Simple SQL migration runner for development / installer reuse.
 */
class Migrator
{
  /** @var array<string, mixed> */
  private array $config;

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(array $config)
  {
    $this->config = $config;
  }

  /**
   * @return list<string>
   */
  public function runAll(): array
  {
    $dir = FORMFLOW_ROOT . '/core/migrations';
    $files = glob($dir . '/*.sql');
    if ($files === false) {
      return [];
    }

    sort($files);
    $applied = [];

    Database::resetInstance();
    $db = Database::getInstance($this->config);
    $prefix = (string) ($this->config['database']['prefix'] ?? '');

    foreach ($files as $file) {
      $sql = (string) file_get_contents($file);
      if ($sql === '') {
        continue;
      }

      if ($prefix !== '') {
        $sql = $this->applyPrefix($sql, $prefix);
      }

      foreach ($this->splitStatements($sql) as $statement) {
        if (trim($statement) === '') {
          continue;
        }

        $db->pdo()->exec($statement);
      }

      $applied[] = basename($file);
    }

    return $applied;
  }

  private function applyPrefix(string $sql, string $prefix): string
  {
    $tables = [
      'users', 'password_resets', 'email_verifications', 'invites',
      'remember_tokens', 'login_attempts', 'audit_log',
      'forms', 'submissions',
      'submission_files', 'submission_rate_log', 'submission_dedup',
      'form_templates', 'api_keys',
    ];

    foreach ($tables as $table) {
      $prefixed = $prefix . $table;
      $sql = preg_replace('/\b' . preg_quote($table, '/') . '\b/', $prefixed, $sql) ?? $sql;
    }

    return $sql;
  }

  /**
   * @return list<string>
   */
  private function splitStatements(string $sql): array
  {
    $parts = preg_split('/;\s*\n/', $sql) ?: [];

    return array_map('trim', $parts);
  }
}
