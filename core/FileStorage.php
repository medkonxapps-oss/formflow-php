<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Secure file upload handling for public submissions.
 */
class FileStorage
{
  /** @var list<string> */
  private const ALLOWED_MIMES = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  ];

  /** @var array<string, mixed> */
  private array $config;

  private string $tblFiles;

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(array $config)
  {
    $this->config = $config;
    $this->tblFiles = Db::table('submission_files', $config);
  }

  /**
   * @param array<string, mixed>|null $file $_FILES entry
   */
  public function validate(?array $file, int $maxBytes, bool $required): ?string
  {
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
      return $required ? 'This field is required.' : null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
      return 'Upload failed.';
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
      return 'File exceeds maximum allowed size.';
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
      return 'Invalid upload.';
    }

    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: 'application/octet-stream';

    if (!in_array($mime, self::ALLOWED_MIMES, true)) {
      return 'File type not allowed.';
    }

    if (self::containsPhpCode($tmp)) {
      return 'File type not allowed.';
    }

    return null;
  }

  /**
   * @param array<string, mixed>|null $file $_FILES entry
   * @return array{stored: bool, meta?: array<string, mixed>, error?: string}
   */
  public function store(int $formId, int $submissionId, string $fieldName, ?array $file, int $maxBytes): array
  {
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
      return ['stored' => false];
    }

    $validationError = $this->validate($file, $maxBytes, false);
    if ($validationError !== null) {
      return ['stored' => false, 'error' => $validationError];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: 'application/octet-stream';
    $size = (int) ($file['size'] ?? 0);

    $ext = self::extensionForMime($mime);
    $storedName = bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
    $dir = FORMFLOW_ROOT . '/uploads/' . $formId;

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
      return ['stored' => false, 'error' => 'Could not create upload directory.'];
    }

    $relativePath = 'uploads/' . $formId . '/' . $storedName;
    $fullPath = FORMFLOW_ROOT . '/' . $relativePath;

    if (!move_uploaded_file($tmp, $fullPath)) {
      return ['stored' => false, 'error' => 'Could not save file.'];
    }

    $original = self::sanitizeFilename(basename((string) ($file['name'] ?? 'file')));
    $db = Database::getInstance($this->config);
    $db->query(
      "INSERT INTO {$this->tblFiles} (submission_id, field_name, original_filename, stored_path, mime_type, size, created_at)
       VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())",
      [$submissionId, $fieldName, $original, $relativePath, $mime, $size]
    );

    return [
      'stored' => true,
      'meta' => [
        'id' => (int) $db->pdo()->lastInsertId(),
        'field_name' => $fieldName,
        'original_filename' => $original,
        'mime_type' => $mime,
        'size' => $size,
      ],
    ];
  }

  /**
   * @return array<string, mixed>|null
   */
  public function findForSubmission(int $fileId, int $submissionId): ?array
  {
    $db = Database::getInstance($this->config);

    return $db->fetchOne(
      "SELECT * FROM {$this->tblFiles} WHERE id = ? AND submission_id = ? LIMIT 1",
      [$fileId, $submissionId]
    );
  }

  private static function extensionForMime(string $mime): string
  {
    return match ($mime) {
      'image/jpeg' => 'jpg',
      'image/png' => 'png',
      'image/gif' => 'gif',
      'image/webp' => 'webp',
      'application/pdf' => 'pdf',
      'application/msword' => 'doc',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
      default => '',
    };
  }

  private static function sanitizeFilename(string $name): string
  {
    $name = preg_replace('/[\x00-\x1F\x7F"\\\\]/', '', $name) ?? 'file';

    return $name !== '' ? substr($name, 0, 255) : 'file';
  }

  private static function containsPhpCode(string $path): bool
  {
    $head = (string) @file_get_contents($path, false, null, 0, 8192);

    return str_contains($head, '<?php') || str_contains($head, '<?=');
  }
}
