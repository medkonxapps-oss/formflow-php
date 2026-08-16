<?php

declare(strict_types=1);

namespace FormFlow;

use PDO;

/**
 * Bundled form template library (PRD §5.7).
 */
class TemplateRepository
{
  private Database $db;

  private string $tbl;

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(private array $config)
  {
    $this->db = Database::getInstance($config);
    $this->tbl = Db::table('form_templates', $config);
  }

  /**
   * @return list<array<string, mixed>>
   */
  public function listAll(): array
  {
    $stmt = $this->db->query(
      "SELECT id, slug, name, description, category, sort_order, created_at, fields_json
       FROM {$this->tbl}
       ORDER BY sort_order ASC, name ASC"
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
  }

  /**
   * @return array<string, mixed>|null
   */
  public function find(int $id): ?array
  {
    $row = $this->db->fetchOne("SELECT * FROM {$this->tbl} WHERE id = ? LIMIT 1", [$id]);

    return $row === null ? null : $this->hydrate($row);
  }

  /**
   * @param array<string, mixed> $row
   * @return array<string, mixed>
   */
  private function hydrate(array $row): array
  {
    $row['fields'] = JsonColumn::decode($row['fields_json'] ?? null);
    $row['settings'] = JsonColumn::decode($row['settings_json'] ?? null);
    unset($row['fields_json'], $row['settings_json']);

    return $row;
  }
}
