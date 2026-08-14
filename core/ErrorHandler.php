<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Production-safe error handling — no stack traces to clients.
 */
class ErrorHandler
{
  public static function register(): void
  {
    if (FORMFLOW_DEBUG) {
      ini_set('display_errors', '1');
      error_reporting(E_ALL);

      return;
    }

    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);

    set_exception_handler([self::class, 'handleException']);
    set_error_handler([self::class, 'handleError']);
  }

  public static function handleException(\Throwable $e): void
  {
    error_log('FormFlow uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    if (!headers_sent()) {
      http_response_code(500);
      header('Content-Type: text/html; charset=utf-8');
    }

    echo self::genericHtml(500);
  }

  /**
   * @return bool
   */
  public static function handleError(int $severity, string $message, string $file, int $line): bool
  {
    if (!(error_reporting() & $severity)) {
      return false;
    }

    error_log("FormFlow PHP error [{$severity}]: {$message} in {$file}:{$line}");

    throw new \ErrorException($message, 0, $severity, $file, $line);
  }

  private static function genericHtml(int $code): string
  {
    $title = $code === 500 ? 'Internal Server Error' : 'Error';

    return '<!DOCTYPE html><html><head><title>' . $title . '</title></head><body><h1>'
      . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
      . '</h1><p>An unexpected error occurred. Please try again later.</p></body></html>';
  }
}
