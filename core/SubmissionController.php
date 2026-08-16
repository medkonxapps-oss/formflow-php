<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Submission inbox HTTP actions.
 */
class SubmissionController
{
  /** @var array<string, mixed> */
  private array $config;

  private Auth $auth;

  private FormRepository $forms;

  private SubmissionRepository $submissions;

  /** @var array<string, string> */
  private array $routeParams;

  /**
   * @param array<string, mixed> $config
   * @param array<string, string> $routeParams
   */
  public function __construct(array $config, array $routeParams = [])
  {
    $this->config = $config;
    $this->routeParams = $routeParams;
    $this->auth = new Auth($config);
    $this->forms = new FormRepository($config);
    $this->submissions = new SubmissionRepository($config);
  }

  public function bulk(): void
  {
    $this->requireEditor();
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token.');
      redirect('/admin/forms');
    }

    $user = $this->auth->user();
    $formId = (int) ($_POST['form_id'] ?? 0);
    $action = (string) ($_POST['bulk_action'] ?? '');
    $ids = array_map('intval', (array) ($_POST['submission_ids'] ?? []));
    $ids = array_values(array_filter($ids, fn ($id) => $id > 0));

    if ($user === null || $action === '') {
      redirect('/admin/submissions');
    }

    if ($formId > 0 && $this->forms->findForUser($formId, (int) $user['id']) === null) {
      flash('error', 'Form not found.');
      redirect('/admin/submissions');
    }

    $count = $this->submissions->bulkAction((int) $user['id'], $action, $ids, $formId);
    Csrf::rotate();
    if ($count === 0) {
      flash('error', 'No submissions were updated. Select at least one row and a bulk action.');
    } else {
      flash('success', "Updated {$count} submission(s).");
    }

    $redirectUrl = $formId > 0 ? "/admin/submissions?form_id={$formId}" : "/admin/submissions";
    redirect($redirectUrl);
  }

  public function single(): void
  {
    $this->requireEditor();
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token.');
      redirect('/admin/submissions');
    }

    $user = $this->auth->user();
    $formId = (int) ($_POST['form_id'] ?? 0);
    $submissionId = (int) ($_POST['submission_id'] ?? 0);
    $action = (string) ($_POST['bulk_action'] ?? '');

    if ($user === null || $formId <= 0 || $submissionId <= 0 || $action === '') {
      flash('error', 'Invalid action.');
      redirect('/admin/submissions');
    }

    $count = $this->submissions->bulkAction((int) $user['id'], $action, [$submissionId], $formId);
    Csrf::rotate();
    flash($count > 0 ? 'success' : 'error', $count > 0 ? 'Submission updated.' : 'Could not update submission.');

    if ($action === 'delete') {
      redirect('/admin/submissions');
    }
    redirect('/admin/forms/' . $formId . '/submissions/' . $submissionId);
  }

  public function addNote(): void
  {
    $this->requireEditor();
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token.');
      redirect('/admin/submissions');
    }

    $user = $this->auth->user();
    $formId = (int) ($_POST['form_id'] ?? 0);
    $submissionId = (int) ($_POST['submission_id'] ?? 0);
    $body = (string) ($_POST['body'] ?? '');

    if ($user === null || $formId <= 0 || $submissionId <= 0) {
      redirect('/admin/submissions');
    }

    $ok = $this->submissions->addNote($submissionId, $formId, (int) $user['id'], $body);
    Csrf::rotate();
    flash($ok ? 'success' : 'error', $ok ? 'Note added.' : 'Could not add note.');
    redirect('/admin/forms/' . $formId . '/submissions/' . $submissionId);
  }

  public function export(): void
  {
    if (!$this->auth->requireRole('viewer')) {
      exit;
    }

    $user = $this->auth->user();
    $formId = (int) ($this->routeParams['formId'] ?? $_GET['form_id'] ?? 0);

    if ($user === null) {
      http_response_code(404);
      exit;
    }

    $filters = $this->filtersFromRequest();

    if ($formId > 0) {
      $form = $this->forms->findForUser($formId, (int) $user['id']);
      if ($form === null) {
        http_response_code(404);
        exit;
      }
      $rows = $this->submissions->exportForForm($formId, (int) $user['id'], $filters);
      $columns = $this->fieldColumns(is_array($form['fields'] ?? null) ? $form['fields'] : []);
      $slug = (string) ($form['slug'] ?? 'form');
    } else {
      $rows = $this->submissions->exportForUser((int) $user['id'], $filters);
      $columns = $this->unionFieldColumns($rows);
      $slug = 'all';
    }

    while (ob_get_level() > 0) {
      ob_end_clean();
    }

    $filename = 'submissions-' . preg_replace('/[^a-z0-9_-]+/i', '-', $slug) . '-' . gmdate('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    if ($out === false) {
      exit;
    }

    // UTF-8 BOM so Excel does not scramble non-English / special characters.
    fwrite($out, "\xEF\xBB\xBF");

    $header = ['ID', 'Form', 'Submitted At', 'IP', 'Referrer', 'Read', 'Starred', 'Spam'];
    foreach ($columns as $label) {
      $header[] = $label;
    }
    $this->writeCsvRow($out, $header);

    foreach ($rows as $row) {
      $data = is_array($row['data'] ?? null) ? $row['data'] : [];
      $line = [
        (string) ($row['id'] ?? ''),
        (string) ($row['form_name'] ?? ($form['name'] ?? '')),
        (string) ($row['created_at'] ?? ''),
        (string) ($row['ip_address'] ?? ''),
        (string) ($row['referrer'] ?? ''),
        !empty($row['is_read']) ? 'Yes' : 'No',
        !empty($row['is_starred']) ? 'Yes' : 'No',
        !empty($row['is_spam']) ? 'Yes' : 'No',
      ];
      foreach (array_keys($columns) as $fieldId) {
        $line[] = $this->csvValue($data[$fieldId] ?? '');
      }
      $this->writeCsvRow($out, $line);
    }

    fclose($out);
    exit;
  }

  /**
   * @param list<array<string, mixed>> $fields
   * @return array<string, string> field_id => label
   */
  private function fieldColumns(array $fields): array
  {
    $columns = [];
    foreach ($fields as $field) {
      if (!is_array($field)) {
        continue;
      }
      $type = (string) ($field['type'] ?? '');
      if (in_array($type, ['heading', 'paragraph', 'hidden'], true)) {
        continue;
      }
      $id = (string) ($field['id'] ?? '');
      if ($id !== '') {
        $columns[$id] = (string) ($field['label'] ?? $id);
      }
    }

    return $columns;
  }

  /**
   * @param list<array<string, mixed>> $rows
   * @return array<string, string>
   */
  private function unionFieldColumns(array $rows): array
  {
    $columns = [];
    foreach ($rows as $row) {
      $data = is_array($row['data'] ?? null) ? $row['data'] : [];
      foreach ($data as $key => $value) {
        $id = (string) $key;
        if ($id === '' || isset($columns[$id])) {
          continue;
        }
        $columns[$id] = $id;
      }
    }

    return $columns;
  }

  /**
   * @param resource $out
   * @param list<string> $line
   */
  private function writeCsvRow($out, array $line): void
  {
    // Empty escape char = RFC 4180 (Excel-safe). PHP's default backslash corrupts quotes.
    fputcsv($out, $line, ',', '"', '');
  }

  private function csvValue(mixed $value): string
  {
    if ($value === null) {
      return '';
    }
    if (is_bool($value)) {
      return $value ? 'Yes' : 'No';
    }
    if (is_array($value)) {
      $parts = [];
      foreach ($value as $item) {
        if (is_array($item)) {
          $parts[] = json_encode($item, JSON_UNESCAPED_UNICODE);
        } else {
          $parts[] = (string) $item;
        }
      }
      $value = implode('; ', $parts);
    } else {
      $value = (string) $value;
    }

    $value = str_replace(["\r\n", "\r"], "\n", $value);
    if ($value !== '' && preg_match('/^[=+\-@]/', $value) === 1) {
      $value = "'" . $value;
    }

    return $value;
  }

  private function requireEditor(): void
  {
    if (!$this->auth->requireRole('editor')) {
      exit;
    }
  }

  /**
   * @return array<string, mixed>
   */
  private function filtersFromRequest(): array
  {
    return [
      'q' => trim((string) ($_GET['q'] ?? $_POST['q'] ?? '')),
      'is_read' => $_GET['is_read'] ?? $_POST['is_read'] ?? '',
      'is_starred' => $_GET['is_starred'] ?? $_POST['is_starred'] ?? '',
      'is_spam' => isset($_GET['is_spam']) || isset($_POST['is_spam'])
        ? (int) ($_GET['is_spam'] ?? $_POST['is_spam'] ?? 0)
        : null,
      'date_from' => (string) ($_GET['date_from'] ?? $_POST['date_from'] ?? ''),
      'date_to' => (string) ($_GET['date_to'] ?? $_POST['date_to'] ?? ''),
    ];
  }

  private function returnUrl(int $formId): string
  {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $base = '/admin/forms/' . $formId . '/submissions';

    return $query !== '' ? $base . '?' . $query : $base;
  }
}
