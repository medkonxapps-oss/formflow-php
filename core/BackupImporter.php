<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Restore a FormFlow JSON backup (tables, settings, uploads).
 */
class BackupImporter
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
   * @param array<string, mixed> $file $_FILES entry
   * @return array{ok: bool, message: string}
   */
  public function importUpload(array $file): array
  {
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
      return ['ok' => false, 'message' => 'Choose a FormFlow JSON backup file.'];
    }
    if ($error !== UPLOAD_ERR_OK) {
      return ['ok' => false, 'message' => 'Upload failed. Try a smaller file or raise PHP upload limits.'];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
      return ['ok' => false, 'message' => 'Invalid upload.'];
    }

    $raw = file_get_contents($tmp);
    if ($raw === false || $raw === '') {
      return ['ok' => false, 'message' => 'Backup file is empty.'];
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
      return ['ok' => false, 'message' => 'Backup is not valid JSON. Use Export JSON, not SQL.'];
    }

    return $this->importPayload($payload);
  }

  /**
   * @param array<string, mixed> $payload
   * @return array{ok: bool, message: string}
   */
  public function importPayload(array $payload): array
  {
    if (($payload['format'] ?? '') !== BackupExporter::FORMAT) {
      return ['ok' => false, 'message' => 'This file is not a FormFlow backup.'];
    }

    $tables = $payload['tables'] ?? null;
    if (!is_array($tables)) {
      return ['ok' => false, 'message' => 'Backup has no table data.'];
    }

    $pdo = $this->db->pdo();
    $restored = 0;

    try {
      $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
      $pdo->beginTransaction();

      foreach (array_reverse(BackupExporter::TABLES) as $logical) {
        $physical = Db::table($logical, $this->config);
        if (!$this->tableExists($physical)) {
          continue;
        }
        $pdo->exec('DELETE FROM `' . str_replace('`', '``', $physical) . '`');
      }

      foreach (BackupExporter::TABLES as $logical) {
        $physical = Db::table($logical, $this->config);
        if (!$this->tableExists($physical)) {
          continue;
        }
        $rows = $tables[$logical] ?? [];
        if (!is_array($rows)) {
          continue;
        }
        foreach ($rows as $row) {
          if (!is_array($row) || $row === []) {
            continue;
          }
          $this->insertRow($physical, $row);
          $restored++;
        }
      }

      $pdo->commit();
      $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    } catch (\Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
      } catch (\Throwable $ignored) {
      }

      return [
        'ok' => false,
        'message' => FORMFLOW_DEBUG ? ('Import failed: ' . $e->getMessage()) : 'Import failed. The current database was not changed.',
      ];
    }

    $this->restoreSettings(is_array($payload['settings'] ?? null) ? $payload['settings'] : []);
    $files = $this->restoreFiles(is_array($payload['files'] ?? null) ? $payload['files'] : []);

    return [
      'ok' => true,
      'message' => "Imported {$restored} row(s) and {$files} file(s). Sign in again if your session dropped.",
    ];
  }

  /**
   * @param array<string, mixed> $row
   */
  private function insertRow(string $table, array $row): void
  {
    $cols = [];
    $vals = [];
    foreach ($row as $key => $value) {
      $cols[] = '`' . str_replace('`', '``', (string) $key) . '`';
      $vals[] = $value;
    }

    $placeholders = implode(', ', array_fill(0, count($vals), '?'));
    $sql = 'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')';
    $this->db->query($sql, $vals);
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

  /**
   * @param array<string, mixed> $settings
   */
  private function restoreSettings(array $settings): void
  {
    if ($settings === []) {
      return;
    }

    $app = is_array($settings['app'] ?? null) ? $settings['app'] : [];
    $smtp = is_array($settings['smtp'] ?? null) ? $settings['smtp'] : [];
    $security = is_array($settings['security'] ?? null) ? $settings['security'] : [];

    $currentSecret = (string) ($this->config['app']['secret'] ?? '');
    $backupSecret = (string) ($app['secret'] ?? '');
    $password = (string) ($smtp['password'] ?? '');
    if ($password !== '' && Crypto::isEncrypted($password) && $backupSecret !== '' && $currentSecret !== '') {
      $plain = Crypto::decrypt($password, $backupSecret);
      if ($plain !== '') {
        $smtp['password'] = Crypto::encrypt($plain, $currentSecret);
      }
    }

    $manager = new ConfigManager($this->config);
    $patch = [];
    if ($app !== []) {
      $patch['app'] = array_intersect_key($app, array_flip(['name', 'timezone', 'locale', 'date_format']));
    }
    if ($smtp !== []) {
      $patch['smtp'] = $smtp;
    }
    if ($security !== []) {
      $patch['security'] = $security;
    }
    if ($patch !== []) {
      $manager->save($patch);
    }
  }

  /**
   * @param list<mixed> $files
   */
  private function restoreFiles(array $files): int
  {
    $count = 0;
    foreach ($files as $file) {
      if (!is_array($file)) {
        continue;
      }
      $path = str_replace('\\', '/', (string) ($file['path'] ?? ''));
      $data = (string) ($file['data'] ?? '');
      if ($data === '' || !preg_match('#^uploads/[0-9]+/[A-Za-z0-9._-]+$#', $path)) {
        continue;
      }
      $basename = basename($path);
      if ($basename[0] === '.') {
        continue;
      }
      $binary = base64_decode($data, true);
      if ($binary === false) {
        continue;
      }
      $full = FORMFLOW_ROOT . '/' . $path;
      $dir = dirname($full);
      if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        continue;
      }
      if (file_put_contents($full, $binary) !== false) {
        $count++;
      }
    }

    return $count;
  }
}
