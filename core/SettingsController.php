<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Admin settings HTTP actions (PRD §5.8) — admin role only.
 */
class SettingsController
{
  /** @var array<string, mixed> */
  private array $config;

  private Auth $auth;

  private ConfigManager $configManager;

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(array $config)
  {
    $this->config = $config;
    $this->auth = new Auth($config);
    $this->configManager = new ConfigManager($config);
  }

  public function saveGeneral(): void
  {
    $this->requireAdmin();
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token.');
      redirect('/admin/settings?tab=general');
    }

    $ok = $this->configManager->save([
      'app' => [
        'name' => trim((string) ($_POST['site_name'] ?? '')),
        'timezone' => trim((string) ($_POST['timezone'] ?? 'UTC')),
        'date_format' => trim((string) ($_POST['date_format'] ?? 'Y-m-d')),
        'locale' => trim((string) ($_POST['locale'] ?? 'en')),
      ],
    ]);

    Csrf::rotate();
    flash($ok ? 'success' : 'error', $ok ? 'General settings saved.' : 'Could not save settings.');
    redirect('/admin/settings?tab=general');
  }

  public function saveSmtp(): void
  {
    $this->requireAdmin();
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token.');
      redirect('/admin/settings?tab=smtp');
    }

    $encryption = strtolower(trim((string) ($_POST['encryption'] ?? 'tls')));
    if (!in_array($encryption, ['tls', 'ssl', ''], true)) {
      $encryption = 'tls';
    }

    $ok = $this->configManager->save([
      'smtp' => [
        'host' => trim((string) ($_POST['host'] ?? '')),
        'port' => (int) ($_POST['port'] ?? 587),
        'username' => trim((string) ($_POST['username'] ?? '')),
        'password' => (string) ($_POST['password'] ?? ''),
        'encryption' => $encryption,
        'from_email' => trim((string) ($_POST['from_email'] ?? '')),
        'from_name' => trim((string) ($_POST['from_name'] ?? '')),
      ],
    ]);

    Csrf::rotate();
    flash($ok ? 'success' : 'error', $ok ? 'SMTP settings saved.' : 'Could not save SMTP settings.');
    redirect('/admin/settings?tab=smtp');
  }

  public function testSmtp(): void
  {
    $this->requireAdmin();
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token.');
      redirect('/admin/settings?tab=smtp');
    }

    $to = trim((string) ($_POST['test_email'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
      flash('error', 'Enter a valid test email address.');
      redirect('/admin/settings?tab=smtp');
    }

    $mailConfig = $this->config;
    $mailConfig['smtp'] = $this->configManager->smtpForMailer();
    $mailer = new Mailer($mailConfig);
    $sent = $mailer->send($to, 'FormFlow SMTP test', '<p>SMTP configuration is working.</p>');

    Csrf::rotate();
    flash($sent ? 'success' : 'error', $sent ? 'Test email sent.' : 'Test email failed. Check SMTP settings.');
    redirect('/admin/settings?tab=smtp');
  }

  public function saveSecurity(): void
  {
    $this->requireAdmin();
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token.');
      redirect('/admin/settings?tab=security');
    }

    $allow = array_values(array_filter(array_map('trim', explode("\n", (string) ($_POST['ip_allowlist'] ?? '')))));
    $block = array_values(array_filter(array_map('trim', explode("\n", (string) ($_POST['ip_blocklist'] ?? '')))));

    $ok = $this->configManager->save([
      'security' => [
        'recaptcha_site_key' => trim((string) ($_POST['recaptcha_site_key'] ?? '')),
        'recaptcha_secret_key' => trim((string) ($_POST['recaptcha_secret_key'] ?? '')),
        'rate_limit_per_minute' => max(1, (int) ($_POST['rate_limit_per_minute'] ?? 10)),
        'ip_allowlist' => $allow,
        'ip_blocklist' => $block,
        'session_timeout_minutes' => max(5, (int) ($_POST['session_timeout_minutes'] ?? 120)),
      ],
    ]);

    Csrf::rotate();
    flash($ok ? 'success' : 'error', $ok ? 'Security settings saved.' : 'Could not save security settings.');
    redirect('/admin/settings?tab=security');
  }

  public function generateApiKey(): void
  {
    $this->requireAdmin();
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token.');
      redirect('/admin/settings?tab=api');
    }

    $user = $this->auth->user();
    if ($user === null) {
      redirect('/login');
    }

    $repo = new ApiKeyRepository($this->config);
    $result = $repo->generate((int) $user['id'], (string) ($_POST['key_name'] ?? 'API Key'));

    Csrf::rotate();
    if (!$result['success']) {
      flash('error', $result['error'] ?? 'Could not generate key.');
    } else {
      $_SESSION['_new_api_key'] = $result['raw_key'];
      flash('success', 'API key created. Copy it now — it will not be shown again.');
    }

    redirect('/admin/settings?tab=api');
  }

  public function revokeApiKey(): void
  {
    $this->requireAdmin();
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token.');
      redirect('/admin/settings?tab=api');
    }

    $user = $this->auth->user();
    $keyId = (int) ($_POST['key_id'] ?? 0);
    if ($user === null || $keyId <= 0) {
      redirect('/admin/settings?tab=api');
    }

    $repo = new ApiKeyRepository($this->config);
    $repo->revoke($keyId, (int) $user['id']);

    Csrf::rotate();
    flash('success', 'API key revoked.');
    redirect('/admin/settings?tab=api');
  }

  public function exportSql(): void
  {
    $this->requireAdmin();
    $exporter = new BackupExporter($this->config);
    $sql = $exporter->exportSql();

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="formflow-backup-' . gmdate('Y-m-d') . '.sql"');
    echo $sql;
    exit;
  }

  public function exportJson(): void
  {
    $this->requireAdmin();
    $exporter = new BackupExporter($this->config);
    $json = $exporter->exportJson();

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="formflow-backup-' . gmdate('Y-m-d') . '.json"');
    echo $json;
    exit;
  }

  private function requireAdmin(): void
  {
    if (!$this->auth->requireRole('admin')) {
      exit;
    }
  }
}
