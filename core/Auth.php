<?php

declare(strict_types=1);

namespace FormFlow;

use PDO;
use RuntimeException;

/**
 * Authentication & authorization — PRD §5.2 / §6.5 Layer 2.
 */
class Auth
{
  private const REMEMBER_COOKIE = 'formflow_remember';
  private const REMEMBER_DAYS = 30;
  private const RESET_EXPIRY_MINUTES = 45;
  private const MIN_PASSWORD_LENGTH = 12;
  private const BACKOFF_THRESHOLD = 5;
  private const CAPTCHA_THRESHOLD = 10;
  private const LOCKOUT_THRESHOLD = 15;
  private const LOCKOUT_WINDOW_SECONDS = 3600;
  private const LOCKOUT_DURATION_SECONDS = 3600;
  private const MAX_BACKOFF_SECONDS = 60;
  private const PASSWORD_RESET_RATE_LIMIT = 5;
  private const PASSWORD_RESET_RATE_WINDOW = 3600;

  /** @var array<string, mixed> */
  private array $config;

  private Database $db;

  private Mailer $mailer;

  private string $tblUsers;
  private string $tblPasswordResets;
  private string $tblRememberTokens;
  private string $tblLoginAttempts;
  private string $tblAuditLog;

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(array $config)
  {
    $this->config = $config;
    $this->tblUsers = Db::table('users', $config);
    $this->tblPasswordResets = Db::table('password_resets', $config);
    $this->tblRememberTokens = Db::table('remember_tokens', $config);
    $this->tblLoginAttempts = Db::table('login_attempts', $config);
    $this->tblAuditLog = Db::table('audit_log', $config);
    $this->db = Database::getInstance($config);
    $this->mailer = new Mailer($config);
  }

  /**
   * Internal registration — installer & invite acceptance only. Never a public route.
   *
   * @return array{success: bool, user_id?: int, error?: string}
   */
  public function register(string $name, string $email, string $password, string $role = 'admin', bool $emailVerified = true): array
  {
    $name = trim($name);
    $email = strtolower(trim($email));

    if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return ['success' => false, 'error' => 'Invalid name or email.'];
    }

    $passwordError = $this->validatePassword($password);
    if ($passwordError !== null) {
      return ['success' => false, 'error' => $passwordError];
    }

    $existing = $this->db->fetchOne("SELECT id FROM {$this->tblUsers} WHERE email = ? LIMIT 1", [$email]);
    if ($existing !== null) {
      return ['success' => false, 'error' => 'Email already registered.'];
    }

    $hash = $this->hashPassword($password);
    $verifiedAt = $emailVerified ? gmdate('Y-m-d H:i:s') : null;

    $this->db->query(
      "INSERT INTO {$this->tblUsers} (name, email, password_hash, role, email_verified_at, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
      [$name, $email, $hash, $role, $verifiedAt]
    );

    $userId = (int) $this->db->pdo()->lastInsertId();

    $this->audit($userId, 'user.registered', ['email' => $email, 'role' => $role]);

    return ['success' => true, 'user_id' => $userId];
  }

  /**
   * @return array{success: bool, error?: string, requires_captcha?: bool, backoff_seconds?: int}
   */
  public function attemptLogin(string $email, string $password, string $ip, string $userAgent, bool $remember = false): array
  {
    $email = strtolower(trim($email));
    $ip = $this->normalizeIp($ip);
    $userAgent = substr($userAgent, 0, 512);

    $backoff = $this->getBackoffSeconds($email);
    if ($backoff > 0) {
      return [
        'success' => false,
        'error' => 'Too many failed attempts. Please wait before trying again.',
        'backoff_seconds' => $backoff,
      ];
    }

    $user = $this->db->fetchOne("SELECT * FROM {$this->tblUsers} WHERE email = ? LIMIT 1", [$email]);

    if ($user !== null && $this->isAccountLocked($user)) {
      $this->recordLoginAttempt($email, 'email', false, $ip, $userAgent);
      $this->recordLoginAttempt($ip, 'ip', false, $ip, $userAgent);
      $this->audit((int) $user['id'], 'login.blocked_locked', ['ip' => $ip]);

      return ['success' => false, 'error' => 'Invalid email or password.'];
    }

    $requiresCaptcha = $this->requiresCaptcha($email);

    $valid = $user !== null
      && $user['email_verified_at'] !== null
      && password_verify($password, (string) $user['password_hash']);

    if (!$valid) {
      $this->recordLoginAttempt($email, 'email', false, $ip, $userAgent);
      $this->recordLoginAttempt($ip, 'ip', false, $ip, $userAgent);

      if ($user !== null) {
        $this->incrementFailedLogin((int) $user['id']);
        $this->handlePostFailureLockout((int) $user['id'], $email, $ip);
        $this->audit((int) $user['id'], 'login.failed', ['ip' => $ip]);
      } else {
        $this->audit(null, 'login.failed_unknown_email', ['email' => $email, 'ip' => $ip]);
      }

      return [
        'success' => false,
        'error' => 'Invalid email or password.',
        'requires_captcha' => $requiresCaptcha || $this->requiresCaptcha($email),
      ];
    }

    $this->recordLoginAttempt($email, 'email', true, $ip, $userAgent);
    $this->recordLoginAttempt($ip, 'ip', true, $ip, $userAgent);

    $this->db->query(
      "UPDATE {$this->tblUsers} SET failed_login_count = 0, locked_until = NULL, last_login_at = UTC_TIMESTAMP(), last_login_ip = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?",
      [$ip, (int) $user['id']]
    );

    Session::regenerate();

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_role'] = (string) $user['role'];
    $_SESSION['authenticated_at'] = gmdate('Y-m-d H:i:s');
    $_SESSION['session_fingerprint'] = $this->sessionFingerprint($ip, $userAgent);

    if ($remember) {
      $this->issueRememberToken((int) $user['id']);
    }

    $this->audit((int) $user['id'], 'login.success', ['ip' => $ip, 'remember' => $remember]);

    return ['success' => true];
  }

  public function logout(): void
  {
    $userId = $this->userId();

    if (isset($_COOKIE[self::REMEMBER_COOKIE])) {
      $this->revokeRememberToken((string) $_COOKIE[self::REMEMBER_COOKIE]);
      $this->clearRememberCookie();
    }

    if ($userId !== null) {
      $this->audit($userId, 'logout', ['ip' => $this->clientIp()]);
    }

    Session::destroy();
  }

  /**
   * Always returns the same generic result — prevents account enumeration.
   *
   * @return array{message: string}
   */
  public function requestPasswordReset(string $email, string $ip): array
  {
    $email = strtolower(trim($email));
    $ip = $this->normalizeIp($ip);
    $generic = ['message' => 'If that email exists, a reset link has been sent.'];

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return $generic;
    }

    if ($this->isPasswordResetRateLimited($email, $ip)) {
      return $generic;
    }

    $user = $this->db->fetchOne("SELECT id, name, email FROM {$this->tblUsers} WHERE email = ? LIMIT 1", [$email]);

    if ($user === null) {
      $this->audit(null, 'password_reset.request_unknown', ['email' => $email, 'ip' => $ip]);

      return $generic;
    }

    $tokenRaw = bin2hex(random_bytes(32));
    $tokenHash = $this->hashToken($tokenRaw);
    $expiresAt = gmdate('Y-m-d H:i:s', time() + (self::RESET_EXPIRY_MINUTES * 60));

    $this->db->query(
      "UPDATE {$this->tblPasswordResets} SET used_at = UTC_TIMESTAMP()
       WHERE user_id = ? AND used_at IS NULL",
      [(int) $user['id']]
    );

    $this->db->query(
      "INSERT INTO {$this->tblPasswordResets} (user_id, token_hash, expires_at, created_at)
       VALUES (?, ?, ?, UTC_TIMESTAMP())",
      [(int) $user['id'], $tokenHash, $expiresAt]
    );

    $resetUrl = app_url($this->config, '/reset-password/' . $tokenRaw);
    $appName = (string) ($this->config['app']['name'] ?? 'FormFlow');

    $this->mailer->send(
      (string) $user['email'],
      $appName . ' — Password Reset',
      '<p>Hello ' . htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') . ',</p>'
      . '<p>Click the link below to reset your password. This link expires in ' . self::RESET_EXPIRY_MINUTES . ' minutes and can only be used once.</p>'
      . '<p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">Reset your password</a></p>'
      . '<p>If you did not request this, you can safely ignore this email.</p>'
    );

    $this->audit((int) $user['id'], 'password_reset.requested', ['ip' => $ip]);

    return $generic;
  }

  /**
   * @return array{success: bool, error?: string}
   */
  public function resetPassword(string $tokenRaw, string $newPassword, string $ip): array
  {
    $tokenRaw = trim($tokenRaw);

    if ($tokenRaw === '') {
      return ['success' => false, 'error' => 'Invalid or expired reset link.'];
    }

    $passwordError = $this->validatePassword($newPassword);
    if ($passwordError !== null) {
      return ['success' => false, 'error' => $passwordError];
    }

    $tokenHash = $this->hashToken($tokenRaw);

    $reset = $this->db->fetchOne(
      "SELECT pr.*, u.email, u.name
       FROM {$this->tblPasswordResets} pr
       INNER JOIN {$this->tblUsers} u ON u.id = pr.user_id
       WHERE pr.token_hash = ? AND pr.used_at IS NULL
       LIMIT 1",
      [$tokenHash]
    );

    if ($reset === null || strtotime((string) $reset['expires_at']) < time()) {
      return ['success' => false, 'error' => 'Invalid or expired reset link.'];
    }

    $userId = (int) $reset['user_id'];
    $hash = $this->hashPassword($newPassword);

    $this->db->query(
      "UPDATE {$this->tblUsers} SET password_hash = ?, failed_login_count = 0, locked_until = NULL, updated_at = UTC_TIMESTAMP() WHERE id = ?",
      [$hash, $userId]
    );

    $this->db->query(
      "UPDATE {$this->tblPasswordResets} SET used_at = UTC_TIMESTAMP() WHERE id = ?",
      [(int) $reset['id']]
    );

    $this->db->query(
      "UPDATE {$this->tblPasswordResets} SET used_at = UTC_TIMESTAMP()
       WHERE user_id = ? AND used_at IS NULL",
      [$userId]
    );

    $this->invalidateAllSessionsForUser($userId);

    $appName = (string) ($this->config['app']['name'] ?? 'FormFlow');
    $this->mailer->send(
      (string) $reset['email'],
      $appName . ' — Password Changed',
      '<p>Hello ' . htmlspecialchars((string) $reset['name'], ENT_QUOTES, 'UTF-8') . ',</p>'
      . '<p>Your password was just changed. If this was not you, secure your account immediately by resetting your password again and reviewing active sessions.</p>'
    );

    $this->audit($userId, 'password_reset.completed', ['ip' => $ip]);

    return ['success' => true];
  }

  public function check(): bool
  {
    return $this->user() !== null;
  }

  /**
   * @return array<string, mixed>|null
   */
  public function user(): ?array
  {
    if (!$this->sessionIsValid()) {
      return null;
    }

    $userId = (int) $_SESSION['user_id'];
    $user = $this->db->fetchOne("SELECT * FROM {$this->tblUsers} WHERE id = ? LIMIT 1", [$userId]);

    if ($user === null) {
      $this->logout();

      return null;
    }

    if ($user['email_verified_at'] === null) {
      $this->logout();

      return null;
    }

    $authenticatedAt = (string) ($_SESSION['authenticated_at'] ?? '');
    $updatedAt = (string) ($user['updated_at'] ?? '');

    if ($authenticatedAt !== '' && $updatedAt !== '' && strtotime($updatedAt) > strtotime($authenticatedAt)) {
      $this->logout();

      return null;
    }

    return $user;
  }

  public function userId(): ?int
  {
    $user = $this->user();

    return $user === null ? null : (int) $user['id'];
  }

  /**
   * Middleware — redirect to login if unauthenticated.
   */
  public function requireAuth(): bool
  {
    if ($this->user() !== null) {
      return true;
    }

    flash('error', 'Please sign in to continue.');
    redirect('/login');

    return false;
  }

  /**
   * Middleware — require a specific role (independent server-side check).
   */
  public function requireRole(string $role): bool
  {
    if (!$this->requireAuth()) {
      return false;
    }

    $user = $this->user();
    $userRole = (string) ($user['role'] ?? '');

    $allowed = $this->roleMeetsRequirement($userRole, $role);

    if (!$allowed) {
      http_response_code(403);
      flash('error', 'You do not have permission to access this page.');
      redirect('/admin');

      return false;
    }

    return true;
  }

  public function attemptRememberLogin(string $ip, string $userAgent): bool
  {
    if (!isset($_COOKIE[self::REMEMBER_COOKIE])) {
      return false;
    }

    $raw = (string) $_COOKIE[self::REMEMBER_COOKIE];
    if ($raw === '' || !str_contains($raw, ':')) {
      $this->clearRememberCookie();

      return false;
    }

    [$selector, $validator] = explode(':', $raw, 2);
    if ($selector === '' || $validator === '') {
      $this->clearRememberCookie();

      return false;
    }

    $token = $this->db->fetchOne(
      "SELECT rt.*, u.role, u.email_verified_at
       FROM {$this->tblRememberTokens} rt
       INNER JOIN {$this->tblUsers} u ON u.id = rt.user_id
       WHERE rt.token_hash = ? AND rt.revoked_at IS NULL AND rt.expires_at > UTC_TIMESTAMP()
       LIMIT 1",
      [$this->hashToken($selector . ':' . $validator)]
    );

    if ($token === null || $token['email_verified_at'] === null) {
      $this->clearRememberCookie();

      return false;
    }

    Session::regenerate();

    $_SESSION['user_id'] = (int) $token['user_id'];
    $_SESSION['user_role'] = (string) $token['role'];
    $_SESSION['authenticated_at'] = gmdate('Y-m-d H:i:s');
    $_SESSION['session_fingerprint'] = $this->sessionFingerprint($ip, $userAgent);

    $this->rotateRememberToken((int) $token['id'], (int) $token['user_id']);
    $this->audit((int) $token['user_id'], 'login.remember_me', ['ip' => $ip]);

    return true;
  }

  public function requiresCaptcha(string $email): bool
  {
    $failures = $this->countRecentFailures($email, 'email', self::LOCKOUT_WINDOW_SECONDS);

    return $failures >= self::CAPTCHA_THRESHOLD;
  }

  public function getBackoffSeconds(string $email): int
  {
    $emailFailures = $this->countRecentFailures($email, 'email', self::LOCKOUT_WINDOW_SECONDS);

    if ($emailFailures < self::BACKOFF_THRESHOLD) {
      return 0;
    }

    $exponent = min($emailFailures - self::BACKOFF_THRESHOLD, 6);

    return (int) min(2 ** $exponent, self::MAX_BACKOFF_SECONDS);
  }

  public function validatePassword(string $password): ?string
  {
    if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
      return 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.';
    }

    return null;
  }

  private function sessionIsValid(): bool
  {
    if (empty($_SESSION['user_id'])) {
      return false;
    }

    $fingerprint = (string) ($_SESSION['session_fingerprint'] ?? '');
    $expected = $this->sessionFingerprint($this->clientIp(), substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512));

    return $fingerprint !== '' && hash_equals($fingerprint, $expected);
  }

  /**
   * @param array<string, mixed> $user
   */
  private function isAccountLocked(array $user): bool
  {
    if (empty($user['locked_until'])) {
      return false;
    }

    return strtotime((string) $user['locked_until']) > time();
  }

  private function incrementFailedLogin(int $userId): void
  {
    $this->db->query(
      "UPDATE {$this->tblUsers} SET failed_login_count = failed_login_count + 1, updated_at = UTC_TIMESTAMP() WHERE id = ?",
      [$userId]
    );
  }

  private function handlePostFailureLockout(int $userId, string $email, string $ip): void
  {
    $failures = $this->countRecentFailures($email, 'email', self::LOCKOUT_WINDOW_SECONDS);

    if ($failures < self::LOCKOUT_THRESHOLD) {
      return;
    }

    $lockedUntil = gmdate('Y-m-d H:i:s', time() + self::LOCKOUT_DURATION_SECONDS);

    $this->db->query(
      "UPDATE {$this->tblUsers} SET locked_until = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?",
      [$lockedUntil, $userId]
    );

    $user = $this->db->fetchOne("SELECT name, email FROM {$this->tblUsers} WHERE id = ? LIMIT 1", [$userId]);
    if ($user === null) {
      return;
    }

    $appName = (string) ($this->config['app']['name'] ?? 'FormFlow');
    $this->mailer->send(
      (string) $user['email'],
      $appName . ' — Suspicious Login Activity',
      '<p>Hello ' . htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') . ',</p>'
      . '<p>Someone attempted to sign in to your account multiple times and it has been temporarily locked.</p>'
      . '<p>If this was you, wait an hour and try again. If not, reset your password immediately.</p>'
      . '<p>IP address: ' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . '</p>'
    );

    $this->audit($userId, 'login.account_locked', ['ip' => $ip, 'failures' => $failures]);
  }

  private function recordLoginAttempt(string $identifier, string $type, bool $success, string $ip, string $userAgent): void
  {
    $this->db->query(
      "INSERT INTO {$this->tblLoginAttempts} (identifier, identifier_type, success, ip_address, user_agent, created_at)
       VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())",
      [$identifier, $type, $success ? 1 : 0, $ip, $userAgent]
    );
  }

  private function countRecentFailures(string $identifier, string $type, int $windowSeconds): int
  {
    $since = gmdate('Y-m-d H:i:s', time() - $windowSeconds);

    $row = $this->db->fetchOne(
      "SELECT COUNT(*) AS cnt FROM {$this->tblLoginAttempts}
       WHERE identifier = ? AND identifier_type = ? AND success = 0 AND created_at >= ?",
      [$identifier, $type, $since]
    );

    return (int) ($row['cnt'] ?? 0);
  }

  private function isPasswordResetRateLimited(string $email, string $ip): bool
  {
    $since = gmdate('Y-m-d H:i:s', time() - self::PASSWORD_RESET_RATE_WINDOW);

    $emailCount = $this->db->fetchOne(
      "SELECT COUNT(*) AS cnt FROM {$this->tblAuditLog}
       WHERE action = ? AND JSON_UNQUOTE(JSON_EXTRACT(meta_json, \"$.email\")) = ? AND created_at >= ?",
      ['password_reset.request_unknown', $email, $since]
    );

    $user = $this->db->fetchOne("SELECT id FROM {$this->tblUsers} WHERE email = ? LIMIT 1", [$email]);
    $userRequests = 0;

    if ($user !== null) {
      $row = $this->db->fetchOne(
        "SELECT COUNT(*) AS cnt FROM {$this->tblAuditLog}
         WHERE action = ? AND user_id = ? AND created_at >= ?",
        ['password_reset.requested', (int) $user['id'], $since]
      );
      $userRequests = (int) ($row['cnt'] ?? 0);
    }

    $ipCount = $this->db->fetchOne(
      "SELECT COUNT(*) AS cnt FROM {$this->tblAuditLog}
       WHERE action IN (?, ?) AND ip_address = ? AND created_at >= ?",
      ['password_reset.requested', 'password_reset.request_unknown', $ip, $since]
    );

    $totalEmail = $userRequests + (int) ($emailCount['cnt'] ?? 0);

    return $totalEmail >= self::PASSWORD_RESET_RATE_LIMIT
      || (int) ($ipCount['cnt'] ?? 0) >= self::PASSWORD_RESET_RATE_LIMIT;
  }

  private function issueRememberToken(int $userId): void
  {
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $tokenHash = $this->hashToken($selector . ':' . $validator);
    $expiresAt = gmdate('Y-m-d H:i:s', time() + (self::REMEMBER_DAYS * 86400));

    $this->db->query(
      "INSERT INTO {$this->tblRememberTokens} (user_id, token_hash, expires_at, created_at)
       VALUES (?, ?, ?, UTC_TIMESTAMP())",
      [$userId, $tokenHash, $expiresAt]
    );

    $this->setRememberCookie($selector . ':' . $validator);
  }

  private function rotateRememberToken(int $tokenId, int $userId): void
  {
    $this->db->query(
      "UPDATE {$this->tblRememberTokens} SET revoked_at = UTC_TIMESTAMP() WHERE id = ?",
      [$tokenId]
    );

    $this->issueRememberToken($userId);
  }

  private function revokeRememberToken(string $cookieValue): void
  {
    if (!str_contains($cookieValue, ':')) {
      return;
    }

    [$selector, $validator] = explode(':', $cookieValue, 2);
    $hash = $this->hashToken($selector . ':' . $validator);

    $this->db->query(
      "UPDATE {$this->tblRememberTokens} SET revoked_at = UTC_TIMESTAMP()
       WHERE token_hash = ? AND revoked_at IS NULL",
      [$hash]
    );
  }

  private function invalidateAllSessionsForUser(int $userId): void
  {
    $this->db->query(
      "UPDATE {$this->tblRememberTokens} SET revoked_at = UTC_TIMESTAMP()
       WHERE user_id = ? AND revoked_at IS NULL",
      [$userId]
    );

    $this->db->query(
      "UPDATE {$this->tblUsers} SET updated_at = UTC_TIMESTAMP() WHERE id = ?",
      [$userId]
    );

    if ($this->userId() === $userId) {
      Session::destroy();
      $this->clearRememberCookie();
    }
  }

  private function setRememberCookie(string $value): void
  {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    if (isset($this->config['app']['session_secure'])) {
      $secure = (bool) $this->config['app']['session_secure'];
    }

    setcookie(self::REMEMBER_COOKIE, $value, [
      'expires' => time() + (self::REMEMBER_DAYS * 86400),
      'path' => '/',
      'secure' => $secure,
      'httponly' => true,
      'samesite' => 'Strict',
    ]);
  }

  private function clearRememberCookie(): void
  {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    setcookie(self::REMEMBER_COOKIE, '', [
      'expires' => time() - 3600,
      'path' => '/',
      'secure' => $secure,
      'httponly' => true,
      'samesite' => 'Strict',
    ]);
  }

  /**
   * @param array<string, mixed>|null $meta
   */
  private function audit(?int $userId, string $action, ?array $meta = null): void
  {
    $this->db->query(
      "INSERT INTO {$this->tblAuditLog} (user_id, action, meta_json, ip_address, created_at)
       VALUES (?, ?, ?, ?, UTC_TIMESTAMP())",
      [
        $userId,
        $action,
        $meta === null ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
        $this->clientIp(),
      ]
    );
  }

  private function hashPassword(string $password): string
  {
    if (defined('PASSWORD_ARGON2ID')) {
      return password_hash($password, PASSWORD_ARGON2ID);
    }

    return password_hash($password, PASSWORD_DEFAULT);
  }

  private function hashToken(string $token): string
  {
    $secret = (string) ($this->config['app']['secret'] ?? '');

    return hash_hmac('sha256', $token, $secret);
  }

  private function sessionFingerprint(string $ip, string $userAgent): string
  {
    $secret = (string) ($this->config['app']['secret'] ?? '');

    return hash_hmac('sha256', $ip . '|' . $userAgent, $secret);
  }

  private function clientIp(): string
  {
    return $this->normalizeIp((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
  }

  private function normalizeIp(string $ip): string
  {
    return substr(trim($ip), 0, 45);
  }

  private function roleMeetsRequirement(string $userRole, string $requiredRole): bool
  {
    $hierarchy = ['viewer' => 1, 'editor' => 2, 'admin' => 3];

    $userLevel = $hierarchy[$userRole] ?? 0;
    $requiredLevel = $hierarchy[$requiredRole] ?? 0;

    return $userLevel >= $requiredLevel;
  }
}
