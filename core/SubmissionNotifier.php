<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Best-effort submission email notifications.
 */
class SubmissionNotifier
{
  /** @var array<string, mixed> */
  private array $config;

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(array $config)
  {
    $this->config = $config;
  }

  /**
   * @param array<string, mixed> $form
   * @param array<string, mixed> $payload
   */
  public function notify(array $form, array $payload): void
  {
    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $notifications = is_array($settings['notifications'] ?? null) ? $settings['notifications'] : [];
    $recipients = is_array($notifications['recipients'] ?? null) ? $notifications['recipients'] : [];

    if ($recipients === []) {
      return;
    }

    $subject = (string) ($notifications['subject'] ?? 'New form submission');
    $formName = (string) ($form['name'] ?? 'Form');
    $body = '<h2>New submission: ' . htmlspecialchars($formName, ENT_QUOTES, 'UTF-8') . '</h2><ul>';

    foreach ($payload as $key => $value) {
      if (is_array($value)) {
        $value = implode(', ', $value);
      }
      if (str_starts_with((string) $key, '_')) {
        continue;
      }
      $body .= '<li><strong>' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') . ':</strong> '
        . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    $body .= '</ul>';

    $mailer = new Mailer($this->config);

    foreach ($recipients as $email) {
      $email = trim((string) $email);
      if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mailer->send($email, $subject, $body);
      }
    }
  }
}
