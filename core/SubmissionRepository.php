<?php

declare(strict_types=1);

namespace FormFlow;

use PDO;

/**
 * Submissions data access — scoped via form ownership.
 */
class SubmissionRepository
{
  private Database $db;

  private string $tblForms;
  private string $tblSubmissions;

  /** @var array<string, mixed> */
  private array $config;

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(array $config)
  {
    $this->config = $config;
    $this->db = Database::getInstance($config);
    $this->tblForms = Db::table('forms', $config);
    $this->tblSubmissions = Db::table('submissions', $config);
  }

  /**
   * @param array<string, mixed> $filters
   * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int}
   */
  public function listForForm(int $formId, int $userId, array $filters = [], int $page = 1, int $perPage = 20): array
  {
    if ($this->formRepository()->findForUser($formId, $userId) === null) {
      return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
    }

    [$where, $params] = $this->buildWhere($formId, $filters);
    $offset = max(0, ($page - 1) * $perPage);

    $countRow = $this->db->fetchOne(
      "SELECT COUNT(*) AS cnt FROM {$this->tblSubmissions} s WHERE {$where}",
      $params
    );
    $total = (int) ($countRow['cnt'] ?? 0);

    $params[] = $perPage;
    $params[] = $offset;

    $stmt = $this->db->query(
      "SELECT s.* FROM {$this->tblSubmissions} s
       WHERE {$where}
       ORDER BY s.created_at DESC
       LIMIT ? OFFSET ?",
      $params
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $items = is_array($rows) ? array_map(fn ($r) => $this->hydrate($r), $rows) : [];

    return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
  }

  /**
   * @return array<string, mixed>|null
   */
  public function findForForm(int $submissionId, int $formId, int $userId): ?array
  {
    if ($this->formRepository()->findForUser($formId, $userId) === null) {
      return null;
    }

    $row = $this->db->fetchOne(
      "SELECT s.* FROM {$this->tblSubmissions} s
       WHERE s.id = ? AND s.form_id = ? LIMIT 1",
      [$submissionId, $formId]
    );

    return $row === null ? null : $this->hydrate($row);
  }

  /**
   * @param list<int> $ids
   * @param array<string, mixed> $filters
   */
  public function bulkAction(int $formId, int $userId, string $action, array $ids, array $filters = []): int
  {
    if ($this->formRepository()->findForUser($formId, $userId) === null || $ids === []) {
      return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$formId], $ids);

    return match ($action) {
      'read' => $this->db->query(
        "UPDATE {$this->tblSubmissions} SET is_read = 1 WHERE form_id = ? AND id IN ({$placeholders})",
        $params
      )->rowCount(),
      'unread' => $this->db->query(
        "UPDATE {$this->tblSubmissions} SET is_read = 0 WHERE form_id = ? AND id IN ({$placeholders})",
        $params
      )->rowCount(),
      'star' => $this->db->query(
        "UPDATE {$this->tblSubmissions} SET is_starred = 1 WHERE form_id = ? AND id IN ({$placeholders})",
        $params
      )->rowCount(),
      'unstar' => $this->db->query(
        "UPDATE {$this->tblSubmissions} SET is_starred = 0 WHERE form_id = ? AND id IN ({$placeholders})",
        $params
      )->rowCount(),
      'spam' => $this->db->query(
        "UPDATE {$this->tblSubmissions} SET is_spam = 1 WHERE form_id = ? AND id IN ({$placeholders})",
        $params
      )->rowCount(),
      'not_spam' => $this->db->query(
        "UPDATE {$this->tblSubmissions} SET is_spam = 0 WHERE form_id = ? AND id IN ({$placeholders})",
        $params
      )->rowCount(),
      'delete' => $this->db->query(
        "DELETE FROM {$this->tblSubmissions} WHERE form_id = ? AND id IN ({$placeholders})",
        $params
      )->rowCount(),
      default => 0,
    };
  }

  /**
   * @param array<string, mixed> $filters
   * @return list<array<string, mixed>>
   */
  public function exportForForm(int $formId, int $userId, array $filters = []): array
  {
    $form = $this->formRepository()->findForUser($formId, $userId);
    if ($form === null) {
      return [];
    }

    [$where, $params] = $this->buildWhere($formId, $filters);

    $stmt = $this->db->query(
      "SELECT s.* FROM {$this->tblSubmissions} s
       WHERE {$where}
       ORDER BY s.created_at DESC",
      $params
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? array_map(fn ($r) => $this->hydrate($r), $rows) : [];
  }

  public function markRead(int $submissionId, int $formId, int $userId): void
  {
    if ($this->findForForm($submissionId, $formId, $userId) === null) {
      return;
    }

    $this->db->query(
      "UPDATE {$this->tblSubmissions} SET is_read = 1 WHERE id = ? AND form_id = ?",
      [$submissionId, $formId]
    );
  }

  /**
   * @param array<string, mixed> $filters
   * @return array{0: string, 1: list<mixed>}
   */
  private function buildWhere(int $formId, array $filters): array
  {
    $where = 's.form_id = ?';
    $params = [$formId];

    if (isset($filters['is_spam'])) {
      $where .= ' AND s.is_spam = ?';
      $params[] = (int) $filters['is_spam'];
    }

    if (isset($filters['is_read']) && $filters['is_read'] !== '') {
      $where .= ' AND s.is_read = ?';
      $params[] = (int) $filters['is_read'];
    }

    if (isset($filters['is_starred']) && $filters['is_starred'] !== '') {
      $where .= ' AND s.is_starred = ?';
      $params[] = (int) $filters['is_starred'];
    }

    if (!empty($filters['date_from'])) {
      $where .= ' AND s.created_at >= ?';
      $params[] = $filters['date_from'] . ' 00:00:00';
    }

    if (!empty($filters['date_to'])) {
      $where .= ' AND s.created_at <= ?';
      $params[] = $filters['date_to'] . ' 23:59:59';
    }

    if (!empty($filters['q'])) {
      $where .= ' AND s.data_json LIKE ?';
      $params[] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['q']) . '%';
    }

    return [$where, $params];
  }

  /**
   * @param array<string, mixed> $row
   * @return array<string, mixed>
   */
  private function hydrate(array $row): array
  {
    $row['data'] = JsonColumn::decode($row['data_json'] ?? null);
    unset($row['data_json']);

    return $row;
  }

  private function formRepository(): FormRepository
  {
    return new FormRepository($this->config);
  }
}
