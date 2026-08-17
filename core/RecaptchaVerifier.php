<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Google reCAPTCHA server-side verification.
 */
class RecaptchaVerifier
{
  private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
  private const TIMEOUT_SECONDS = 3;

  /** @return 'ok'|'invalid'|'unavailable' */
  public static function verify(string $secret, string $token, string $ip): string
  {
    if ($secret === '' || $token === '') {
      return 'invalid';
    }

    $post = http_build_query([
      'secret' => $secret,
      'response' => $token,
      'remoteip' => $ip,
    ]);

    $context = stream_context_create([
      'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $post,
        'timeout' => self::TIMEOUT_SECONDS,
        'ignore_errors' => true,
      ],
    ]);

    $result = @file_get_contents(self::VERIFY_URL, false, $context);
    if ($result === false) {
      return 'unavailable';
    }

    $data = json_decode($result, true);
    if (!is_array($data)) {
      return 'unavailable';
    }

    if (empty($data['success'])) {
      return 'invalid';
    }

    $expectedHost = (string) parse_url('https://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    $hostname = (string) ($data['hostname'] ?? '');
    if ($expectedHost !== '' && $hostname !== '' && $hostname !== $expectedHost) {
      return 'invalid';
    }

    return 'ok';
  }
}
