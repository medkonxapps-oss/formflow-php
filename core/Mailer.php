<?php

declare(strict_types=1);

namespace FormFlow;

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * SMTP mailer wrapper — PHPMailer vendored under /includes/PHPMailer.
 */
class Mailer
{
  /** @var array<string, mixed> */
  private array $config;

  private string $lastError = '';

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(array $config)
  {
    $this->config = $config;
  }

  public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
  {
    require_once FORMFLOW_ROOT . '/includes/PHPMailer/src/Exception.php';
    require_once FORMFLOW_ROOT . '/includes/PHPMailer/src/PHPMailer.php';
    require_once FORMFLOW_ROOT . '/includes/PHPMailer/src/SMTP.php';

    $smtp = $this->resolveSmtpConfig();
    $app = $this->config['app'] ?? [];
    $this->lastError = '';

    $mail = new PHPMailer(true);

    try {
      $host = trim((string) ($smtp['host'] ?? ''));
      $from = trim((string) ($smtp['from_email'] ?? ''));
      if ($host === '') {
        $this->lastError = 'SMTP host is empty.';
        return false;
      }
      if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $this->lastError = 'Set a valid From email in SMTP settings.';
        return false;
      }

      $mail->isSMTP();
      $mail->Timeout = 20;
      $mail->Host = $host;
      $mail->Port = (int) ($smtp['port'] ?? 587);
      $mail->SMTPAuth = !empty($smtp['username']);
      $mail->Username = (string) ($smtp['username'] ?? '');
      $mail->Password = (string) ($smtp['password'] ?? '');

      $encryption = strtolower((string) ($smtp['encryption'] ?? 'tls'));
      if ($encryption === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
      } elseif ($encryption === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
      } else {
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;
      }

      $mail->setFrom(
        $from,
        (string) ($smtp['from_name'] ?? ($app['name'] ?? 'FormFlow'))
      );
      $mail->addAddress($to);
      $mail->isHTML(true);
      $mail->Subject = $subject;
      $mail->Body = $htmlBody;
      $mail->AltBody = $textBody ?? strip_tags($htmlBody);

      $mail->send();

      return true;
    } catch (MailerException $e) {
      $this->lastError = $e->getMessage();
      if (FORMFLOW_DEBUG) {
        error_log('FormFlow Mailer error: ' . $e->getMessage());
      }

      return false;
    }
  }

  public function lastError(): string
  {
    return $this->lastError;
  }

  /**
   * @return array<string, mixed>
   */
  private function resolveSmtpConfig(): array
  {
    $manager = new ConfigManager($this->config);

    return $manager->smtpForMailer();
  }
}
