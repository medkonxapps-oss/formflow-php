<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Per-session CSRF token generation and verification.
 */
class Csrf
{
  private const SESSION_KEY = '_csrf_token';

  public static function token(): string
  {
    if (empty($_SESSION[self::SESSION_KEY])) {
      $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION[self::SESSION_KEY];
  }

  public static function field(): string
  {
    $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');

    return '<input type="hidden" name="_csrf" value="' . $token . '">';
  }

  public static function verify(?string $token): bool
  {
    if ($token === null || $token === '') {
      return false;
    }

    $expected = $_SESSION[self::SESSION_KEY] ?? '';

    if ($expected === '') {
      return false;
    }

    return hash_equals($expected, $token);
  }

  public static function verifyRequest(): bool
  {
    $token = $_POST['_csrf'] ?? null;

    return self::verify(is_string($token) ? $token : null);
  }

  public static function rotate(): void
  {
    $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
  }
}
