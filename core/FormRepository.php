<?php

declare(strict_types=1);

namespace FormFlow;

use PDO;

/**
 * Forms data access — always scoped to owning user (IDOR prevention).
 */
class FormRepository
{
  private Database $db;

  private string $tblForms;
  private string $tblSubmissions;

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(array $config)
  {
    $this->db = Database::getInstance($config);
    $this->tblForms = Db::table('forms', $config);
    $this->tblSubmissions = Db::table('submissions', $config);
  }

  /**
   * @return list<array<string, mixed>>
   */
  public function listForUser(int $userId): array
  {
    $stmt = $this->db->query(
      "SELECT f.*,
        (SELECT COUNT(*) FROM {$this->tblSubmissions} s WHERE s.form_id = f.id AND s.is_spam = 0) AS submission_count
       FROM {$this->tblForms} f
       WHERE f.user_id = ?
       ORDER BY f.updated_at DESC",
      [$userId]
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $this->hydrateForms($rows) : [];
  }

  /**
   * @return array<string, mixed>|null
   */
  public function findForUser(int $formId, int $userId): ?array
  {
    $row = $this->db->fetchOne(
      "SELECT * FROM {$this->tblForms} WHERE id = ? AND user_id = ? LIMIT 1",
      [$formId, $userId]
    );

    return $row === null ? null : $this->hydrateForm($row);
  }

  /**
   * Public lookup for submission endpoint (no user scope).
   *
   * @return array<string, mixed>|null
   */
  public function findPublicBySlugOrId(string $identifier): ?array
  {
    $identifier = trim($identifier);
    if ($identifier === '') {
      return null;
    }

    if (ctype_digit($identifier)) {
      $row = $this->db->fetchOne(
        "SELECT * FROM {$this->tblForms} WHERE id = ? LIMIT 1",
        [(int) $identifier]
      );
    } else {
      $row = $this->db->fetchOne(
        "SELECT * FROM {$this->tblForms} WHERE slug = ? LIMIT 1",
        [$identifier]
      );
    }

    return $row === null ? null : $this->hydrateForm($row);
  }

  /**
   * @param array<string, mixed> $data
   * @return array{success: bool, form_id?: int, error?: string}
   */
  public function create(int $userId, array $data): array
  {
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
      return ['success' => false, 'error' => 'Form name is required.'];
    }

    $slug = $this->uniqueSlug($this->slugify($name));
    $fields = $data['fields'] ?? [];
    $settings = $data['settings'] ?? FormDefaults::settings();
    $status = ($data['status'] ?? 'active') === 'paused' ? 'paused' : 'active';

    if (!is_array($fields)) {
      $fields = [];
    }
    if (!is_array($settings)) {
      $settings = FormDefaults::settings();
    }

    $this->db->query(
      "INSERT INTO {$this->tblForms} (user_id, name, slug, status, fields_json, settings_json, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
      [
        $userId,
        $name,
        $slug,
        $status,
        JsonColumn::encode($fields),
        JsonColumn::encode($settings),
      ]
    );

    return ['success' => true, 'form_id' => (int) $this->db->pdo()->lastInsertId()];
  }

  /**
   * @param array<string, mixed> $data
   * @return array{success: bool, error?: string}
   */
  public function update(int $formId, int $userId, array $data): array
  {
    $form = $this->findForUser($formId, $userId);
    if ($form === null) {
      return ['success' => false, 'error' => 'Form not found.'];
    }

    $name = trim((string) ($data['name'] ?? $form['name']));
    if ($name === '') {
      return ['success' => false, 'error' => 'Form name is required.'];
    }

    $slug = (string) ($form['slug']);
    if (!empty($data['slug'])) {
      $slug = $this->uniqueSlug($this->slugify((string) $data['slug']), $formId);
    }

    $fields = $data['fields'] ?? $form['fields'];
    $settings = $data['settings'] ?? $form['settings'];
    $status = ($data['status'] ?? $form['status']) === 'paused' ? 'paused' : 'active';

    $this->db->query(
      "UPDATE {$this->tblForms}
       SET name = ?, slug = ?, status = ?, fields_json = ?, settings_json = ?, updated_at = UTC_TIMESTAMP()
       WHERE id = ? AND user_id = ?",
      [
        $name,
        $slug,
        $status,
        JsonColumn::encode(is_array($fields) ? $fields : []),
        JsonColumn::encode(is_array($settings) ? $settings : FormDefaults::settings()),
        $formId,
        $userId,
      ]
    );

    return ['success' => true];
  }

  /**
   * @return array{success: bool, form_id?: int, error?: string}
   */
  public function duplicate(int $formId, int $userId): array
  {
    $form = $this->findForUser($formId, $userId);
    if ($form === null) {
      return ['success' => false, 'error' => 'Form not found.'];
    }

    return $this->create($userId, [
      'name' => $form['name'] . ' (Copy)',
      'fields' => $form['fields'],
      'settings' => $form['settings'],
      'status' => 'paused',
    ]);
  }

  public function delete(int $formId, int $userId): bool
  {
    $stmt = $this->db->query(
      "DELETE FROM {$this->tblForms} WHERE id = ? AND user_id = ?",
      [$formId, $userId]
    );

    return $stmt->rowCount() > 0;
  }

  /**
   * @return array{success: bool, status?: string, error?: string}
   */
  public function toggleStatus(int $formId, int $userId): array
  {
    $form = $this->findForUser($formId, $userId);
    if ($form === null) {
      return ['success' => false, 'error' => 'Form not found.'];
    }

    $newStatus = $form['status'] === 'active' ? 'paused' : 'active';

    $this->db->query(
      "UPDATE {$this->tblForms} SET status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ?",
      [$newStatus, $formId, $userId]
    );

    return ['success' => true, 'status' => $newStatus];
  }

  public function slugify(string $text): string
  {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');

    return $text !== '' ? substr($text, 0, 100) : 'form';
  }

  private function uniqueSlug(string $base, ?int $excludeId = null): string
  {
    $slug = $base;
    $i = 1;

    while ($this->slugExists($slug, $excludeId)) {
      $slug = substr($base, 0, 90) . '-' . $i;
      $i++;
    }

    return $slug;
  }

  private function slugExists(string $slug, ?int $excludeId): bool
  {
    if ($excludeId !== null) {
      $row = $this->db->fetchOne(
        "SELECT id FROM {$this->tblForms} WHERE slug = ? AND id != ? LIMIT 1",
        [$slug, $excludeId]
      );
    } else {
      $row = $this->db->fetchOne(
        "SELECT id FROM {$this->tblForms} WHERE slug = ? LIMIT 1",
        [$slug]
      );
    }

    return $row !== null;
  }

  /**
   * @param array<string, mixed> $row
   * @return array<string, mixed>
   */
  private function hydrateForm(array $row): array
  {
    $row['fields'] = JsonColumn::decode($row['fields_json'] ?? null);
    $row['settings'] = JsonColumn::decode($row['settings_json'] ?? null);
    unset($row['fields_json'], $row['settings_json']);

    return $row;
  }

  /**
   * @param list<array<string, mixed>> $rows
   * @return list<array<string, mixed>>
   */
  private function hydrateForms(array $rows): array
  {
    return array_map(fn (array $row) => $this->hydrateForm($row), $rows);
  }
}
