<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Orchestrates public form submission (PRD §5.9 / §6.5 Layer 5).
 */
class SubmissionService
{
  /** @var array<string, mixed> */
  private array $config;

  private FormRepository $forms;

  private Database $db;

  private string $tblSubmissions;

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(array $config)
  {
    $this->config = $config;
    $this->forms = new FormRepository($config);
    $this->db = Database::getInstance($config);
    $this->tblSubmissions = Db::table('submissions', $config);
  }

  /**
   * @return never
   */
  public function handle(string $slugOrId): void
  {
    $wantsJson = $this->wantsJsonResponse();
    $ip = $this->clientIp();
    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
    $referrer = substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 2048);

    $form = $this->forms->findPublicBySlugOrId($slugOrId);

    if ($form === null) {
      $this->respondError($wantsJson, 404, [], 'Form not found.');
    }

    $denied = SecurityGuard::denyReason($this->config, $ip);
    if ($denied !== null) {
      $this->respondError($wantsJson, 403, [], $denied);
    }

    if (!CorsHandler::apply($form, $this->config)) {
      $this->respondError($wantsJson, 403, [], 'Origin not allowed.');
    }

    if (($form['status'] ?? '') !== 'active') {
      $this->respondError($wantsJson, 403, [], 'This form is not accepting submissions.');
    }

    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $rateLimit = is_array($settings['rate_limit'] ?? null) ? $settings['rate_limit'] : [];
    $globalLimit = (int) (is_array($this->config['security'] ?? null) ? ($this->config['security']['rate_limit_per_minute'] ?? 10) : 10);
    $formLimit = (int) ($rateLimit['per_minute'] ?? $globalLimit);
    $perMinute = max(1, min($formLimit, max(1, $globalLimit)));

    $limiter = new SubmissionRateLimiter($this->config);
    if ($limiter->isLimited((int) $form['id'], $ip, $perMinute)) {
      http_response_code(429);
      if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Too many requests. Please try again later.']);
      } else {
        echo 'Too many requests.';
      }
      exit;
    }

    $payload = $this->parsePayload();
    $fields = is_array($form['fields'] ?? null) ? $form['fields'] : [];
    $spam = is_array($settings['spam'] ?? null) ? $settings['spam'] : [];

    $isHoneypotSpam = !empty($spam['honeypot']) && trim((string) ($payload['_honeypot'] ?? '')) !== '';
    unset($payload['_honeypot']);

    if (!$isHoneypotSpam) {
      if (!empty($spam['recaptcha'])) {
        $token = (string) ($payload['g-recaptcha-response'] ?? $_POST['g-recaptcha-response'] ?? '');
        unset($payload['g-recaptcha-response']);
        $secret = (string) ($spam['recaptcha_secret_key'] ?? '');
        if ($secret === '') {
          $secret = (string) (is_array($this->config['security'] ?? null) ? ($this->config['security']['recaptcha_secret_key'] ?? '') : '');
        }
        $result = RecaptchaVerifier::verify($secret, $token, $ip);
        $failMode = (string) ($spam['recaptcha_fail_mode'] ?? 'closed');

        if ($result === 'invalid' || ($result === 'unavailable' && $failMode === 'closed')) {
          $this->respondError($wantsJson, 422, ['_form' => 'CAPTCHA verification failed.'], 'CAPTCHA verification failed.');
        }
      }

      $dedup = new SubmissionDedup($this->config);
      $cached = $dedup->findDuplicate((int) $form['id'], $this->normalizePayload($payload), $ip);
      if ($cached !== null) {
        $this->sendSuccessResponse($form, $cached, $wantsJson);
      }

      $errors = FormValidator::validate($fields, $payload);
      $uploads = is_array($settings['uploads'] ?? null) ? $settings['uploads'] : [];
      $maxBytes = (int) ($uploads['max_bytes'] ?? 5242880);
      $storage = new FileStorage($this->config);

      foreach ($fields as $field) {
        if (!is_array($field) || ($field['type'] ?? '') !== 'file') {
          continue;
        }
        $fieldId = (string) ($field['id'] ?? '');
        if ($fieldId === '') {
          continue;
        }
        $fileError = $storage->validate(
          $_FILES[$fieldId] ?? null,
          $maxBytes,
          !empty($field['required'])
        );
        if ($fileError !== null) {
          $errors[$fieldId] = $fileError;
        }
      }

      if ($errors !== []) {
        $this->respondError($wantsJson, 422, $errors, 'Validation failed.');
      }
    }

    $limiter->record((int) $form['id'], $ip);

    $submissionId = $this->insertSubmission(
      (int) $form['id'],
      $this->normalizePayload($payload),
      $ip,
      $userAgent,
      $referrer,
      $isHoneypotSpam
    );

    if (!$isHoneypotSpam) {
      $maxBytes = (int) (($settings['uploads']['max_bytes'] ?? null) ?: 5242880);
      $storage = new FileStorage($this->config);

    foreach ($fields as $field) {
      if (!is_array($field) || ($field['type'] ?? '') !== 'file') {
        continue;
      }
      $fieldId = (string) ($field['id'] ?? '');
      if ($fieldId === '') {
        continue;
      }
      $file = $_FILES[$fieldId] ?? null;
      if (is_array($file)) {
        $storage->store((int) $form['id'], $submissionId, $fieldId, $file, $maxBytes);
      }
    }

    } // end if (!$isHoneypotSpam) file storage block

    $submission = [
      'id' => $submissionId,
      'data' => $this->normalizePayload($payload),
      'ip_address' => $ip,
      'created_at' => gmdate('Y-m-d H:i:s'),
    ];

    if (!$isHoneypotSpam) {
      try {
        (new SubmissionNotifier($this->config))->notify($form, $submission['data']);
      } catch (\Throwable) {
      }

      try {
        WebhookClient::dispatch($form, $submission);
      } catch (\Throwable) {
      }

      // A/B Test: record conversion if visitor has a variant session
      try {
        $abSessionToken = (string) ($_COOKIE['ff_ab_session'] ?? '');
        if ($abSessionToken !== '') {
          (new AbTestRepository($this->config))->recordConversion(
            (int) $form['id'],
            $abSessionToken,
            (int) $submissionId
          );
        }
      } catch (\Throwable) {
      }
    }

    $success = $this->buildSuccessResponse($form);
    (new SubmissionDedup($this->config))->store(
      (int) $form['id'],
      $this->normalizePayload($payload),
      $ip,
      $submissionId,
      $success
    );

    $this->sendSuccessResponse($form, $success, $wantsJson);
  }

  /**
   * @return array<string, mixed>
   */
  private function parsePayload(): array
  {
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

    if (str_contains($contentType, 'application/json')) {
      $raw = (string) file_get_contents('php://input');
      $decoded = json_decode($raw, true);

      return is_array($decoded) ? $decoded : [];
    }

    $data = $_POST;

    foreach ($_POST as $key => $value) {
      if (is_string($value) && str_ends_with($key, '[]')) {
        continue;
      }
      if (isset($_POST[$key]) && is_array($_POST[$key])) {
        $data[$key] = $_POST[$key];
      }
    }

    return $data;
  }

  /**
   * @param array<string, mixed> $payload
   * @return array<string, mixed>
   */
  private function normalizePayload(array $payload): array
  {
    $normalized = [];
    foreach ($payload as $key => $value) {
      if (str_starts_with((string) $key, '_')) {
        continue;
      }
      if (is_array($value)) {
        $normalized[$key] = array_values($value);
      } else {
        $normalized[$key] = trim((string) $value);
      }
    }
    ksort($normalized);

    return $normalized;
  }

  /**
   * @param array<string, mixed> $data
   */
  private function insertSubmission(int $formId, array $data, string $ip, string $ua, string $referrer, bool $isSpam): int
  {
    $this->db->query(
      "INSERT INTO {$this->tblSubmissions} (form_id, data_json, ip_address, user_agent, referrer, is_spam, is_read, is_starred, created_at)
       VALUES (?, ?, ?, ?, ?, ?, 0, 0, UTC_TIMESTAMP())",
      [
        $formId,
        JsonColumn::encode($data),
        $ip,
        $ua,
        $referrer !== '' ? $referrer : null,
        $isSpam ? 1 : 0,
      ]
    );

    return (int) $this->db->pdo()->lastInsertId();
  }

  /**
   * @param array<string, mixed> $form
   * @return array<string, mixed>
   */
  private function buildSuccessResponse(array $form): array
  {
    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $success = is_array($settings['success'] ?? null) ? $settings['success'] : [];

    return [
      'success' => true,
      'message' => (string) ($success['message'] ?? 'Thank you for your submission!'),
      'redirect_url' => (string) ($success['redirect_url'] ?? ''),
      'type' => (string) ($success['type'] ?? 'message'),
    ];
  }

  /**
   * @param array<string, mixed> $form
   * @param array<string, mixed> $response
   * @return never
   */
  private function sendSuccessResponse(array $form, array $response, bool $wantsJson): void
  {
    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $success = is_array($settings['success'] ?? null) ? $settings['success'] : [];

    if ($wantsJson) {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode([
        'success' => true,
        'message' => $response['message'] ?? $success['message'] ?? 'Thank you!',
      ], JSON_UNESCAPED_UNICODE);
      exit;
    }

    if (($success['type'] ?? 'message') === 'redirect' && !empty($success['redirect_url'])) {
      $redir = (string) $success['redirect_url'];
      $parsed = parse_url($redir);
      $host = strtolower((string) ($parsed['host'] ?? ''));
      $serverHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
      if ($host !== '' && $host !== $serverHost) {
        $redir = '/';
      }
      header('Location: ' . $redir, true, 302);
      exit;
    }

    $message = htmlspecialchars((string) ($response['message'] ?? $success['message'] ?? 'Thank you!'), ENT_QUOTES, 'UTF-8');
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Thank you</title></head><body><p>' . $message . '</p></body></html>';
    exit;
  }

  /**
   * @param array<string, string> $errors
   * @return never
   */
  private function respondError(bool $wantsJson, int $code, array $errors, string $message): void
  {
    http_response_code($code);

    if ($wantsJson) {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['success' => false, 'message' => $message, 'errors' => $errors], JSON_UNESCAPED_UNICODE);
      exit;
    }

    if ($code === 422 && $errors !== []) {
      $_SESSION['_submit_errors'] = $errors;
      $referer = self::safeReferer((string) ($_SERVER['HTTP_REFERER'] ?? ''));
      header('Location: ' . $referer, true, 302);
      exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Error</title></head><body><p>'
      . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></body></html>';
    exit;
  }

  private function wantsJsonResponse(): bool
  {
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

    return str_contains($accept, 'application/json') || str_contains($contentType, 'application/json');
  }

  private function clientIp(): string
  {
    return substr(trim((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')), 0, 45);
  }

  private static function safeReferer(string $referer): string
  {
    if ($referer === '') {
      return '/';
    }

    $parsed = parse_url($referer);
    if (!is_array($parsed)) {
      return '/';
    }

    $host = strtolower((string) ($parsed['host'] ?? ''));
    $serverHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

    if ($host === '' || $serverHost === '' || $host !== $serverHost) {
      return '/';
    }

    $path = (string) ($parsed['path'] ?? '/');
    $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

    return $path . $query;
  }
}
