<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Authenticated download for submission file attachments.
 */
class SubmissionFileController
{
  /** @var array<string, mixed> */
  private array $config;

  /** @var array<string, string> */
  private array $routeParams;

  private Auth $auth;

  /**
   * @param array<string, mixed> $config
   * @param array<string, string> $routeParams
   */
  public function __construct(array $config, array $routeParams = [])
  {
    $this->config = $config;
    $this->routeParams = $routeParams;
    $this->auth = new Auth($config);
  }

  public function download(): void
  {
    if (!$this->auth->requireRole('viewer')) {
      exit;
    }

    $user = $this->auth->user();
    if ($user === null) {
      exit;
    }

    $submissionId = (int) ($this->routeParams['submissionId'] ?? 0);
    $fileId = (int) ($this->routeParams['fileId'] ?? 0);

    if ($submissionId <= 0 || $fileId <= 0) {
      http_response_code(404);
      exit;
    }

    $tblSubmissions = Db::table('submissions', $this->config);
    $tblForms = Db::table('forms', $this->config);
    $db = Database::getInstance($this->config);

    $row = $db->fetchOne(
      "SELECT sf.*, s.form_id, f.user_id
       FROM " . Db::table('submission_files', $this->config) . " sf
       INNER JOIN {$tblSubmissions} s ON s.id = sf.submission_id
       INNER JOIN {$tblForms} f ON f.id = s.form_id
       WHERE sf.id = ? AND sf.submission_id = ? AND f.user_id = ?
       LIMIT 1",
      [$fileId, $submissionId, (int) $user['id']]
    );

    if ($row === null) {
      http_response_code(404);
      exit;
    }

    $path = FORMFLOW_ROOT . '/' . ltrim((string) $row['stored_path'], '/');

    if (!is_readable($path)) {
      http_response_code(404);
      exit;
    }

    $mime = (string) ($row['mime_type'] ?? 'application/octet-stream');
    $filename = self::sanitizeDownloadName((string) ($row['original_filename'] ?? 'download'));

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
    exit;
  }

  private static function sanitizeDownloadName(string $name): string
  {
    $name = preg_replace('/[\x00-\x1F\x7F"\\\\\/]/', '_', $name) ?? 'download';

    return $name !== '' ? substr($name, 0, 255) : 'download';
  }
}
