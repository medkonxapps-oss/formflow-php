<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Best-effort webhook delivery for new submissions.
 */
class WebhookClient
{
  private const TIMEOUT_SECONDS = 3;

  /**
   * @param array<string, mixed> $form
   * @param array<string, mixed> $submission
   */
  public static function dispatch(array $form, array $submission): void
  {
    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $url = trim((string) ($settings['webhook_url'] ?? ''));

    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
      return;
    }

    $parsed = parse_url($url);
    $host = strtolower((string) ($parsed['host'] ?? ''));
    if ($host === '' || self::isPrivateHost($host)) {
      return;
    }

    $payload = json_encode([
      'form_id' => $form['id'] ?? null,
      'form_slug' => $form['slug'] ?? null,
      'submission_id' => $submission['id'] ?? null,
      'data' => $submission['data'] ?? [],
      'ip_address' => $submission['ip_address'] ?? null,
      'created_at' => $submission['created_at'] ?? null,
    ], \JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
      return;
    }

    $context = stream_context_create([
      'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $payload,
        'timeout' => self::TIMEOUT_SECONDS,
        'ignore_errors' => true,
      ],
    ]);

    @file_get_contents($url, false, $context);
  }

  private static function isPrivateHost(string $host): bool
  {
    if ($host === 'localhost' || $host === '0.0.0.0' || $host === '::1' || str_ends_with($host, '.local')) {
      return true;
    }

    $ip = gethostbyname($host);
    if ($ip === $host) {
      return false;
    }

    return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
  }
}
