<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Encrypt/decrypt sensitive config values at rest (PRD §6.5 Layer 6).
 */
class Crypto
{
  private const PREFIX = 'enc:v1:';

  public static function encrypt(string $plaintext, string $secret): string
  {
    if ($plaintext === '') {
      return '';
    }

    $key = hash('sha256', $secret, true);
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    if ($cipher === false) {
      throw new \RuntimeException('Encryption failed.');
    }

    return self::PREFIX . base64_encode($iv . $tag . $cipher);
  }

  public static function decrypt(string $ciphertext, string $secret): string
  {
    if ($ciphertext === '' || !self::isEncrypted($ciphertext)) {
      return $ciphertext;
    }

    $raw = base64_decode(substr($ciphertext, strlen(self::PREFIX)), true);
    if ($raw === false || strlen($raw) < 28) {
      return '';
    }

    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $encrypted = substr($raw, 28);
    $key = hash('sha256', $secret, true);

    $plain = openssl_decrypt($encrypted, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    return $plain === false ? '' : $plain;
  }

  public static function isEncrypted(string $value): bool
  {
    return str_starts_with($value, self::PREFIX);
  }
}
