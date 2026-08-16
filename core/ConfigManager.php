<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Read and persist config.php with encrypted SMTP password support.
 */
class ConfigManager
{
  private string $rootPath;

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(private array $config, ?string $rootPath = null)
  {
    $this->rootPath = $rootPath ?? FORMFLOW_ROOT;
  }

  /**
   * @return array<string, mixed>
   */
  public function all(): array
  {
    return $this->config;
  }

  /**
   * @return array<string, mixed> SMTP block with decrypted password for display (masked) / mailer use
   */
  public function smtpForMailer(): array
  {
    $smtp = is_array($this->config['smtp'] ?? null) ? $this->config['smtp'] : [];
    $secret = (string) ($this->config['app']['secret'] ?? '');
    $password = (string) ($smtp['password'] ?? '');

    if ($password !== '' && Crypto::isEncrypted($password) && $secret !== '') {
      $smtp['password'] = Crypto::decrypt($password, $secret);
    }

    return $smtp;
  }

  /**
   * @param array<string, mixed> $patch Partial config to merge and save
   */
  public function save(array $patch): bool
  {
    $merged = array_replace_recursive($this->config, $patch);

    if (isset($patch['security']) && is_array($patch['security'])) {
      $merged['security'] = array_merge(self::defaultSecurity(), $patch['security']);
    }

    if (isset($patch['smtp']) && is_array($patch['smtp'])) {
      $currentSmtp = is_array($this->config['smtp'] ?? null) ? $this->config['smtp'] : [];
      $merged['smtp'] = array_merge($currentSmtp, $patch['smtp']);
    }

    $secret = (string) ($merged['app']['secret'] ?? '');

    if (isset($merged['smtp']['password'])) {
      $pw = (string) $merged['smtp']['password'];
      if ($pw === '') {
        $merged['smtp']['password'] = $this->config['smtp']['password'] ?? '';
      } elseif (!Crypto::isEncrypted($pw) && $secret !== '') {
        $merged['smtp']['password'] = Crypto::encrypt($pw, $secret);
      }
    }

    $ok = ConfigWriter::write($this->rootPath, $merged);
    if ($ok) {
      $this->config = $merged;
    }

    return $ok;
  }

  /**
   * @return array<string, mixed> Defaults for security section
   */
  public static function defaultSecurity(): array
  {
    return [
      'recaptcha_site_key' => '',
      'recaptcha_secret_key' => '',
      'rate_limit_per_minute' => 10,
      'ip_allowlist' => [],
      'ip_blocklist' => [],
      'session_timeout_minutes' => 120,
    ];
  }

  /**
   * @return array<string, mixed>
   */
  public function security(): array
  {
    $security = is_array($this->config['security'] ?? null) ? $this->config['security'] : [];

    return array_merge(self::defaultSecurity(), $security);
  }
}
