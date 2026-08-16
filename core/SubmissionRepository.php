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
   * @param array<string, mixed> $filters
   * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int}
   */
  public function listForUser(int $userId, array $filters = [], int $page = 1, int $perPage = 20): array
  {
    [$where, $params] = $this->buildWhereForUser($userId, $filters);
    $offset = max(0, ($page - 1) * $perPage);

    $countRow = $this->db->fetchOne(
      "SELECT COUNT(*) AS cnt FROM {$this->tblSubmissions} s INNER JOIN {$this->tblForms} f ON f.id = s.form_id WHERE {$where}",
      $params
    );
    $total = (int) ($countRow['cnt'] ?? 0);

    $params[] = $perPage;
    $params[] = $offset;

    $stmt = $this->db->query(
      "SELECT s.*, f.name AS form_name, f.slug AS form_slug 
       FROM {$this->tblSubmissions} s
       INNER JOIN {$this->tblForms} f ON f.id = s.form_id
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
   */
  public function bulkAction(int $userId, string $action, array $ids, int $formId = 0): int
  {
    $ids = array_values(array_unique(array_filter($ids, static fn ($id) => $id > 0)));
    if ($ids === []) {
      return 0;
    }

    $allowed = $this->ownedIds($userId, $ids, $formId);
    if ($allowed === []) {
      return 0;
    }

    $placeholders = implode(',', array_fill(0, count($allowed), '?'));

    $set = match ($action) {
      'read' => 'is_read = 1',
      'unread' => 'is_read = 0',
      'star' => 'is_starred = 1',
      'unstar' => 'is_starred = 0',
      'spam' => 'is_spam = 1',
      'not_spam' => 'is_spam = 0',
      'delete' => null,
      default => false,
    };

    if ($set === false) {
      return 0;
    }

    if ($set === null) {
      $stmt = $this->db->query(
        "DELETE FROM {$this->tblSubmissions} WHERE id IN ({$placeholders})",
        $allowed
      );

      return $stmt->rowCount();
    }

    $stmt = $this->db->query(
      "UPDATE {$this->tblSubmissions} SET {$set} WHERE id IN ({$placeholders})",
      $allowed
    );

    return $stmt->rowCount();
  }

  /**
   * @param list<int> $ids
   * @return list<int>
   */
  private function ownedIds(int $userId, array $ids, int $formId = 0): array
  {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = [$userId];
    $formSql = '';
    if ($formId > 0) {
      $formSql = ' AND s.form_id = ?';
      $params[] = $formId;
    }
    $params = array_merge($params, $ids);

    $stmt = $this->db->query(
      "SELECT s.id FROM {$this->tblSubmissions} s
       INNER JOIN {$this->tblForms} f ON f.id = s.form_id
       WHERE f.user_id = ?{$formSql} AND s.id IN ({$placeholders})",
      $params
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($rows)) {
      return [];
    }

    return array_map(static fn (array $r): int => (int) $r['id'], $rows);
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

  /**
   * @param array<string, mixed> $filters
   * @return list<array<string, mixed>>
   */
  public function exportForUser(int $userId, array $filters = []): array
  {
    [$where, $params] = $this->buildWhereForUser($userId, $filters);

    $stmt = $this->db->query(
      "SELECT s.*, f.name AS form_name, f.slug AS form_slug 
       FROM {$this->tblSubmissions} s
       INNER JOIN {$this->tblForms} f ON f.id = s.form_id
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

  public function unreadCount(int $userId): int
  {
    $row = $this->db->fetchOne(
      "SELECT COUNT(*) AS cnt
       FROM {$this->tblSubmissions} s
       INNER JOIN {$this->tblForms} f ON f.id = s.form_id
       WHERE f.user_id = ? AND s.is_read = 0 AND s.is_spam = 0",
      [$userId]
    );

    return (int) ($row['cnt'] ?? 0);
  }

  /**
   * @return list<array<string, mixed>>
   */
  public function notesFor(int $submissionId, int $formId, int $userId): array
  {
    if ($this->findForForm($submissionId, $formId, $userId) === null) {
      return [];
    }

    $tbl = Db::table('submission_notes', $this->config);
    $tblUsers = Db::table('users', $this->config);
    $stmt = $this->db->query(
      "SELECT n.id, n.body, n.created_at, u.name AS author
       FROM {$tbl} n
       INNER JOIN {$tblUsers} u ON u.id = n.user_id
       WHERE n.submission_id = ?
       ORDER BY n.created_at ASC",
      [$submissionId]
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
  }

  public function addNote(int $submissionId, int $formId, int $userId, string $body): bool
  {
    $body = trim($body);
    if ($body === '' || $this->findForForm($submissionId, $formId, $userId) === null) {
      return false;
    }

    $tbl = Db::table('submission_notes', $this->config);
    $this->db->query(
      "INSERT INTO {$tbl} (submission_id, user_id, body, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())",
      [$submissionId, $userId, $body]
    );

    return true;
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
   * @param array<string, mixed> $filters
   * @return array{0: string, 1: list<mixed>}
   */
  private function buildWhereForUser(int $userId, array $filters): array
  {
    $where = 'f.user_id = ?';
    $params = [$userId];

    if (!empty($filters['form_id'])) {
      $where .= ' AND s.form_id = ?';
      $params[] = (int) $filters['form_id'];
    }

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
