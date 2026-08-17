<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * REST API authenticated with API keys (Bearer or X-Api-Key).
 */
class ApiController
{
  /** @var array<string, mixed> */
  private array $config;

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
  }

  public function forms(): void
  {
    $user = $this->requireKey();
    $forms = (new FormRepository($this->config))->listForUser((int) $user['id']);
    $out = [];
    foreach ($forms as $form) {
      $out[] = [
        'id' => (int) $form['id'],
        'name' => $form['name'],
        'slug' => $form['slug'],
        'status' => $form['status'],
        'submissions' => (int) ($form['submission_count'] ?? 0),
        'updated_at' => $form['updated_at'],
      ];
    }
    $this->json(['data' => $out]);
  }

  public function submissions(): void
  {
    $user = $this->requireKey();
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $formId = (int) ($_GET['form_id'] ?? 0);
    $result = (new SubmissionRepository($this->config))->listForUser((int) $user['id'], [
      'form_id' => $formId,
      'q' => trim((string) ($_GET['q'] ?? '')),
      'is_spam' => isset($_GET['is_spam']) ? (int) $_GET['is_spam'] : 0,
    ], $page, 50);

    $this->json([
      'data' => array_map(fn (array $row): array => $this->serializeSubmission($row), $result['items']),
      'meta' => [
        'page' => $result['page'] ?? $page,
        'per_page' => $result['per_page'] ?? 50,
        'total' => $result['total'] ?? 0,
      ],
    ]);
  }

  public function submission(): void
  {
    $user = $this->requireKey();
    $id = (int) ($this->routeParams['id'] ?? 0);
    $formId = (int) ($this->routeParams['formId'] ?? $_GET['form_id'] ?? 0);
    $repo = new SubmissionRepository($this->config);

    if ($formId <= 0) {
      $this->json(['error' => 'form_id is required'], 400);
    }

    $row = $repo->findForForm($id, $formId, (int) $user['id']);
    if ($row === null) {
      $this->json(['error' => 'Not found'], 404);
    }

    $this->json(['data' => $this->serializeSubmission($row)]);
  }

  /**
   * @return array<string, mixed>
   */
  private function requireKey(): array
  {
    $raw = $this->extractKey();
    if ($raw === '') {
      $this->json(['error' => 'Missing API key. Use Authorization: Bearer ff_… or X-Api-Key.'], 401);
    }

    $user = (new ApiKeyRepository($this->config))->authenticate($raw);
    if ($user === null) {
      $this->json(['error' => 'Invalid or revoked API key.'], 401);
    }

    return $user;
  }

  private function extractKey(): string
  {
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(\S+)/i', $header, $m) === 1) {
      return trim($m[1]);
    }

    return trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
  }

  /**
   * @param array<string, mixed> $row
   * @return array<string, mixed>
   */
  private function serializeSubmission(array $row): array
  {
    return [
      'id' => (int) $row['id'],
      'form_id' => (int) $row['form_id'],
      'form_name' => $row['form_name'] ?? null,
      'data' => is_array($row['data'] ?? null) ? $row['data'] : [],
      'is_spam' => !empty($row['is_spam']),
      'is_read' => !empty($row['is_read']),
      'is_starred' => !empty($row['is_starred']),
      'created_at' => $row['created_at'] ?? null,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  private function json(array $payload, int $code = 200): void
  {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
  }
}
