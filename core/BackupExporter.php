<?php

declare(strict_types=1);

namespace FormFlow;

use PDO;

/**
 * Full FormFlow backup: real table rows, settings, and uploaded files.
 */
class BackupExporter
{
  public const FORMAT = 'formflow-backup';

  public const VERSION = 1;

  /** Parent-first order for INSERT. */
  public const TABLES = [
    'users',
    'password_resets',
    'email_verifications',
    'invites',
    'remember_tokens',
    'login_attempts',
    'audit_log',
    'forms',
    'submissions',
    'submission_files',
    'submission_rate_log',
    'submission_dedup',
    'form_templates',
    'api_keys',
    'form_views',
    'submission_notes',
    'form_variants',
    'form_variant_sessions',
    'form_variant_conversions',
  ];

  private Database $db;

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(private array $config)
  {
    $this->db = Database::getInstance($config);
  }

  public function exportSql(): string
  {
    $safe = static fn (string $name): string => str_replace('`', '``', $name);
    $out = "-- FormFlow SQL backup\n";
    $out .= '-- Generated: ' . gmdate('Y-m-d H:i:s') . " UTC\n";
    $out .= "-- Restore in phpMyAdmin (Import) or: mysql -u USER -p DATABASE < this-file.sql\n";
    $out .= "-- Uploaded files are not in SQL; use JSON export for those.\n\n";
    $out .= "SET NAMES utf8mb4;\n";
    $out .= "SET TIME_ZONE='+00:00';\n";
    $out .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $out .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";

    foreach (self::TABLES as $logical) {
      $table = $this->physical($logical);
      if (!$this->tableExists($table)) {
        $out .= "-- Skipped missing table `{$table}`\n\n";
        continue;
      }

      $rows = $this->fetchRows($table);
      $out .= "--\n-- Table `{$table}` (" . count($rows) . " row(s))\n--\n\n";

      $ddl = $this->createTableSql($table);
      if ($ddl !== '') {
        $out .= "DROP TABLE IF EXISTS `" . $safe($table) . "`;\n";
        $out .= rtrim($ddl, " \t\n;") . ";\n\n";
      } else {
        $out .= "DELETE FROM `" . $safe($table) . "`;\n";
      }

      if ($rows === []) {
        $out .= "-- No rows in `{$table}`\n\n";
        continue;
      }

      $out .= $this->insertStatements($table, $rows);
      $out .= "\n";
    }

    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";

    return $out;
  }

  /**
   * @param list<array<string, mixed>> $rows
   */
  private function insertStatements(string $table, array $rows): string
  {
    $safeTable = '`' . str_replace('`', '``', $table) . '`';
    $out = '';
    $batch = [];
    $colSql = '';

    foreach ($rows as $row) {
      if ($colSql === '') {
        $cols = array_map(
          static fn ($c) => '`' . str_replace('`', '``', (string) $c) . '`',
          array_keys($row)
        );
        $colSql = implode(', ', $cols);
      }
      $vals = array_map(fn ($v) => $this->sqlValue($v), array_values($row));
      $batch[] = '(' . implode(', ', $vals) . ')';

      if (count($batch) >= 50) {
        $out .= "INSERT INTO {$safeTable} ({$colSql}) VALUES\n" . implode(",\n", $batch) . ";\n";
        $batch = [];
      }
    }

    if ($batch !== []) {
      $out .= "INSERT INTO {$safeTable} ({$colSql}) VALUES\n" . implode(",\n", $batch) . ";\n";
    }

    return $out;
  }

  /**
   * @return string JSON document
   */
  public function exportJson(): string
  {
    $payload = $this->payload();
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    return is_string($json) ? $json : '{}';
  }

  /**
   * @return array<string, mixed>
   */
  public function payload(): array
  {
    $tables = [];
    $counts = [];

    foreach (self::TABLES as $logical) {
      $table = $this->physical($logical);
      if (!$this->tableExists($table)) {
        $tables[$logical] = [];
        $counts[$logical] = 0;
        continue;
      }
      $rows = $this->fetchRows($table);
      $tables[$logical] = $rows;
      $counts[$logical] = count($rows);
    }

    return [
      'format' => self::FORMAT,
      'version' => self::VERSION,
      'exported_at' => gmdate('c'),
      'prefix' => (string) ($this->config['database']['prefix'] ?? ''),
      'counts' => $counts,
      'settings' => $this->exportableSettings(),
      'tables' => $tables,
      'files' => $this->exportUploadFiles(),
    ];
  }

  /**
   * @return list<array<string, mixed>>
   */
  private function fetchRows(string $table): array
  {
    $stmt = $this->db->pdo()->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`');
    if ($stmt === false) {
      return [];
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    return is_array($rows) ? $rows : [];
  }

  private function tableExists(string $table): bool
  {
    try {
      $stmt = $this->db->pdo()->query('SELECT 1 FROM `' . str_replace('`', '``', $table) . '` LIMIT 0');
      if ($stmt === false) {
        return false;
      }
      $stmt->closeCursor();

      return true;
    } catch (\Throwable $e) {
      return false;
    }
  }

  private function createTableSql(string $table): string
  {
    try {
      $stmt = $this->db->pdo()->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`');
      if ($stmt === false) {
        return '';
      }
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      $stmt->closeCursor();
      if (!is_array($row)) {
        return '';
      }

      return (string) ($row['Create Table'] ?? $row['Create View'] ?? '');
    } catch (\Throwable $e) {
      return '';
    }
  }

  private function physical(string $logical): string
  {
    return Db::table($logical, $this->config);
  }

  /**
   * @return array<string, mixed>
   */
  private function exportableSettings(): array
  {
    $app = is_array($this->config['app'] ?? null) ? $this->config['app'] : [];
    $smtp = is_array($this->config['smtp'] ?? null) ? $this->config['smtp'] : [];
    $security = is_array($this->config['security'] ?? null) ? $this->config['security'] : [];

    return [
      'app' => [
        'name' => (string) ($app['name'] ?? 'FormFlow'),
        'secret' => (string) ($app['secret'] ?? ''),
        'timezone' => (string) ($app['timezone'] ?? 'UTC'),
        'locale' => (string) ($app['locale'] ?? 'en'),
        'date_format' => (string) ($app['date_format'] ?? 'Y-m-d'),
      ],
      'smtp' => $smtp,
      'security' => $security,
    ];
  }

  /**
   * @return list<array{path: string, data: string}>
   */
  private function exportUploadFiles(): array
  {
    $root = FORMFLOW_ROOT . '/uploads';
    if (!is_dir($root)) {
      return [];
    }

    $files = [];
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
      if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
        continue;
      }
      $full = $fileInfo->getPathname();
      $relative = str_replace('\\', '/', substr($full, strlen(FORMFLOW_ROOT) + 1));
      if (!preg_match('#^uploads/[0-9]+/[A-Za-z0-9._-]+$#', $relative)) {
        continue;
      }
      if ($fileInfo->getSize() > 8 * 1024 * 1024) {
        continue;
      }
      $binary = file_get_contents($full);
      if ($binary === false) {
        continue;
      }
      $files[] = [
        'path' => $relative,
        'data' => base64_encode($binary),
      ];
    }

    return $files;
  }

  /**
   * @param mixed $value
   */
  private function sqlValue($value): string
  {
    if ($value === null) {
      return 'NULL';
    }

    if (is_int($value) || is_float($value)) {
      return (string) $value;
    }

    return $this->db->pdo()->quote((string) $value);
  }
}
