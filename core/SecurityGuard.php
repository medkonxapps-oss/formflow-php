<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Workspace security settings: IP lists used on public submit and login.
 */
class SecurityGuard
{
  /**
   * @param array<string, mixed> $config
   */
  public static function clientIp(): string
  {
    return substr(trim((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')), 0, 45);
  }

  /**
   * @param array<string, mixed> $config
   */
  public static function denyReason(array $config, string $ip, bool $applyAllowlist = true): ?string
  {
    $security = is_array($config['security'] ?? null) ? $config['security'] : [];
    $allow = self::normalizeList($security['ip_allowlist'] ?? []);
    $block = self::normalizeList($security['ip_blocklist'] ?? []);

    if ($block !== [] && self::matchesAny($ip, $block)) {
      return 'This IP address is blocked.';
    }

    if ($applyAllowlist && $allow !== [] && !self::matchesAny($ip, $allow)) {
      return 'This IP address is not on the allow list.';
    }

    return null;
  }

  /**
   * @param mixed $value
   * @return list<string>
   */
  private static function normalizeList($value): array
  {
    if (!is_array($value)) {
      return [];
    }

    $out = [];
    foreach ($value as $item) {
      $item = trim((string) $item);
      if ($item !== '') {
        $out[] = $item;
      }
    }

    return $out;
  }

  /**
   * @param list<string> $patterns
   */
  private static function matchesAny(string $ip, array $patterns): bool
  {
    foreach ($patterns as $pattern) {
      if (strcasecmp($ip, $pattern) === 0) {
        return true;
      }
      if (str_contains($pattern, '/') && self::cidrMatch($ip, $pattern)) {
        return true;
      }
    }

    return false;
  }

  private static function cidrMatch(string $ip, string $cidr): bool
  {
    $parts = explode('/', $cidr, 2);
    if (count($parts) !== 2 || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || !filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
      return false;
    }

    $mask = (int) $parts[1];
    if ($mask < 0 || $mask > 32) {
      return false;
    }

    $ipLong = ip2long($ip);
    $netLong = ip2long($parts[0]);
    if ($ipLong === false || $netLong === false) {
      return false;
    }

    $maskLong = $mask === 0 ? 0 : (~((1 << (32 - $mask)) - 1) & 0xFFFFFFFF);

    return ($ipLong & $maskLong) === ($netLong & $maskLong);
  }
}
