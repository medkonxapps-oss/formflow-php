<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Secure session bootstrap — HttpOnly, Secure, SameSite=Strict.
 */
class Session
{
  private static bool $started = false;

  /**
   * @param array<string, mixed> $config
   */
  public static function start(array $config): void
  {
    if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
      self::$started = true;
      return;
    }

    $secure = self::shouldUseSecureCookie($config);
    $timeoutMinutes = (int) (is_array($config['security'] ?? null) ? ($config['security']['session_timeout_minutes'] ?? 120) : 120);
    $lifetime = max(300, $timeoutMinutes * 60);

    session_name('formflow_session');
    session_set_cookie_params([
      'lifetime' => $lifetime,
      'path' => '/',
      'domain' => '',
      'secure' => $secure,
      'httponly' => true,
      'samesite' => 'Strict',
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', (string) $lifetime);

    if ($secure) {
      ini_set('session.cookie_secure', '1');
    }

    session_start();
    self::$started = true;
  }

  public static function regenerate(): void
  {
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_regenerate_id(true);
    }
  }

  public static function destroy(): void
  {
    if (session_status() !== PHP_SESSION_ACTIVE) {
      return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        (bool) $params['secure'],
        (bool) $params['httponly']
      );
    }

    session_destroy();
    self::$started = false;
  }

  /**
   * @param array<string, mixed> $config
   */
  private static function shouldUseSecureCookie(array $config): bool
  {
    if (isset($config['app']['session_secure'])) {
      return (bool) $config['app']['session_secure'];
    }

    if (($config['app']['env'] ?? '') === 'production') {
      return true;
    }

    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
  }
}
