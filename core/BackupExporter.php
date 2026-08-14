<?php

declare(strict_types=1);

namespace FormFlow;

use PDO;

/**
 * Full database backup export as SQL or JSON.
 */
class BackupExporter
{
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
    $prefix = (string) ($this->config['database']['prefix'] ?? '');
    $tables = $this->listTables();
    $out = "-- FormFlow backup " . gmdate('Y-m-d H:i:s') . " UTC\n\n";
    $out .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
      $create = $this->db->fetchOne("SHOW CREATE TABLE `{$table}`");
      if ($create === null) {
        continue;
      }
      $ddl = (string) ($create['Create Table'] ?? '');
      $out .= "DROP TABLE IF EXISTS `{$table}`;\n{$ddl};\n\n";

      $stmt = $this->db->pdo()->query("SELECT * FROM `{$table}`");
      if ($stmt === false) {
        continue;
      }

      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!is_array($row)) {
          continue;
        }
        $cols = array_map(fn ($c) => '`' . str_replace('`', '``', $c) . '`', array_keys($row));
        $vals = array_map(fn ($v) => $this->sqlValue($v), array_values($row));
        $out .= 'INSERT INTO `' . $table . '` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ");\n";
      }
      $out .= "\n";
    }

    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";

    return $out;
  }

  /**
   * @return string JSON document
   */
  public function exportJson(): string
  {
    $tables = $this->listTables();
    $data = ['exported_at' => gmdate('c'), 'tables' => []];

    foreach ($tables as $table) {
      $stmt = $this->db->pdo()->query("SELECT * FROM `{$table}`");
      $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
      $data['tables'][$table] = is_array($rows) ? $rows : [];
    }

    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
  }

  /**
   * @return list<string>
   */
  private function listTables(): array
  {
    $dbName = (string) ($this->config['database']['name'] ?? '');
    $stmt = $this->db->pdo()->query('SHOW TABLES');
    $tables = [];
    if ($stmt !== false) {
      while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        if (isset($row[0])) {
          $tables[] = (string) $row[0];
        }
      }
    }

    sort($tables);

    return $tables;
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
