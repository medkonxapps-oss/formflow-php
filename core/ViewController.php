<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Public view-tracking pixel / beacon.
 */
class ViewController
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

  public function embedPreflight(): void
  {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: *');
    header('Access-Control-Allow-Private-Network: true');
    header('Content-Security-Policy: frame-ancestors *');
    http_response_code(204);
    exit;
  }

  public function track(): void
  {
    $slug = (string) ($this->routeParams['slug'] ?? '');
    $forms = new FormRepository($this->config);
    $form = $forms->findPublicBySlugOrId($slug);

    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
      http_response_code(204);
      exit;
    }

    if ($form === null || ($form['status'] ?? '') !== 'active') {
      http_response_code(204);
      exit;
    }

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $referrer = (string) ($_SERVER['HTTP_REFERER'] ?? $_GET['ref'] ?? '');

    try {
      (new ViewTracker($this->config))->record((int) $form['id'], $ip, $referrer);
    } catch (\Throwable $e) {
      // Tracking must never break the public form.
    }

    if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'image/')) {
      header('Content-Type: image/gif');
      echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
      exit;
    }

    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
  }
}
