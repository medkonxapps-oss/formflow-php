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

    if ($user === null || $formId <= 0 || $action === '') {
      redirect('/admin/forms');
    }

    if ($this->forms->findForUser($formId, (int) $user['id']) === null) {
      flash('error', 'Form not found.');
      redirect('/admin/forms');
    }

    $count = $this->submissions->bulkAction($formId, (int) $user['id'], $action, $ids);
    Csrf::rotate();
    flash('success', "Updated {$count} submission(s).");
    redirect($this->returnUrl($formId));
  }

  public function export(): void
  {
    if (!$this->auth->requireRole('viewer')) {
      exit;
    }

    $user = $this->auth->user();
    $formId = (int) ($this->routeParams['formId'] ?? $_GET['form_id'] ?? 0);

    if ($user === null || $formId <= 0) {
      http_response_code(404);
      exit;
    }

    $form = $this->forms->findForUser($formId, (int) $user['id']);
    if ($form === null) {
      http_response_code(404);
      exit;
    }

    $filters = $this->filtersFromRequest();
    $rows = $this->submissions->exportForForm($formId, (int) $user['id'], $filters);
    $fields = is_array($form['fields']) ? $form['fields'] : [];

    $columns = [];
    foreach ($fields as $field) {
      if (!is_array($field)) {
        continue;
      }
      $type = (string) ($field['type'] ?? '');
      if (in_array($type, ['heading', 'paragraph'], true)) {
        continue;
      }
      $id = (string) ($field['id'] ?? '');
      if ($id !== '') {
        $columns[$id] = (string) ($field['label'] ?? $id);
      }
    }

    $filename = 'submissions-' . ($form['slug'] ?? 'export') . '-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    if ($out === false) {
      exit;
    }

    $header = array_merge(['ID', 'Submitted At', 'IP', 'Referrer', 'Read', 'Starred', 'Spam'], array_values($columns));
    fputcsv($out, $header);

    foreach ($rows as $row) {
      $data = is_array($row['data'] ?? null) ? $row['data'] : [];
      $line = [
        $row['id'] ?? '',
        $row['created_at'] ?? '',
        $row['ip_address'] ?? '',
        $row['referrer'] ?? '',
        !empty($row['is_read']) ? 'Yes' : 'No',
        !empty($row['is_starred']) ? 'Yes' : 'No',
        !empty($row['is_spam']) ? 'Yes' : 'No',
      ];
      foreach (array_keys($columns) as $fieldId) {
        $val = $data[$fieldId] ?? '';
        if (is_array($val)) {
          $val = implode(', ', $val);
        }
        $line[] = $val;
      }
      fputcsv($out, $line);
    }

    fclose($out);
    exit;
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
