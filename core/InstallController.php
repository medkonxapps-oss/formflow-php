<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Installer wizard controller (PRD §5.1).
 */
class InstallController
{
  private string $rootPath;

  public function __construct()
  {
    $this->rootPath = FORMFLOW_ROOT;
  }

  public function dispatch(): void
  {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $path = $this->installPath();

    if ($method === 'POST' && $path === '/api/test-database') {
      $this->apiTestDatabase();
      return;
    }

    if ($method === 'POST' && $path === '/api/test-email') {
      $this->apiTestEmail();
      return;
    }

    if ($method === 'POST') {
      $this->handlePost($path);
      return;
    }

    $this->handleGet($path);
  }

  private function installPath(): string
  {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/install/', PHP_URL_PATH) ?: '/install/';
    $uri = '/' . trim($uri, '/');

    if ($uri === '/install') {
      return '/';
    }

    if (str_starts_with($uri, '/install/')) {
      return substr($uri, strlen('/install')) ?: '/';
    }

    return '/';
  }

  private function handleGet(string $path): void
  {
    $slug = trim($path, '/');
    if ($slug === '') {
      $slug = 'requirements';
    }

    $step = InstallState::stepFromSlug($slug);
    InstallState::guardStep($step);

    $view = match ($slug) {
      'requirements' => 'requirements',
      'database' => 'database',
      'smtp' => 'smtp',
      'admin' => 'admin',
      'settings' => 'settings',
      'complete' => 'complete',
      default => null,
    };

    if ($view === null) {
      http_response_code(404);
      echo 'Not found';
      return;
    }

    $this->render($view, $step);
  }

  private function handlePost(string $path): void
  {
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token. Please try again.');
      redirect('/install/');
    }

    $slug = trim($path, '/');

    match ($slug) {
      'requirements' => $this->postRequirements(),
      'database' => $this->postDatabase(),
      'smtp' => $this->postSmtp(),
      'smtp/skip' => $this->postSmtpSkip(),
      'admin' => $this->postAdmin(),
      'settings' => $this->postSettings(),
      'complete' => $this->postComplete(),
      default => redirect('/install/'),
    };
  }

  private function postRequirements(): void
  {
    $result = RequirementsChecker::run($this->rootPath);

    if (!$result['passed']) {
      flash('error', 'Please resolve all failed requirements before continuing.');
      redirect('/install/');
    }

    InstallState::put(['requirements_passed' => true]);
    Csrf::rotate();
    redirect('/install/database');
  }

  private function postDatabase(): void
  {
    $database = $this->databaseFromPost();

    $test = $this->testDatabaseConfig($database);
    if (!$test['success']) {
      flash('error', $test['error'] ?? 'Database connection failed.');
      set_old($_POST);
      redirect('/install/database');
    }

    try {
      Database::resetInstance();
      $config = ['database' => $database, 'app' => ['secret' => 'install-temp-secret']];
      $migrator = new Migrator($config);
      $migrator->runAll();
      TemplateSeeder::run($config);
    } catch (\Throwable $e) {
      flash('error', 'Migration failed: ' . ($e->getMessage()));
      set_old($_POST);
      redirect('/install/database');
    }

    InstallState::put([
      'database' => $database,
      'database_configured' => true,
    ]);
    clear_old();
    Csrf::rotate();
    redirect('/install/smtp');
  }

  private function postSmtp(): void
  {
    $smtp = $this->smtpFromPost();
    InstallState::put([
      'smtp' => $smtp,
      'smtp_configured' => true,
      'smtp_skipped' => false,
    ]);
    Csrf::rotate();
    redirect('/install/admin');
  }

  private function postSmtpSkip(): void
  {
    InstallState::put([
      'smtp' => [
        'host' => '',
        'port' => 587,
        'username' => '',
        'password' => '',
        'encryption' => 'tls',
        'from_email' => '',
        'from_name' => 'FormFlow',
      ],
      'smtp_configured' => true,
      'smtp_skipped' => true,
    ]);
    Csrf::rotate();
    redirect('/install/admin');
  }

  private function postAdmin(): void
  {
    $state = InstallState::get();

    if (!empty($state['admin_created'])) {
      redirect('/install/settings');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirmation'] ?? '');

    if ($password !== $passwordConfirm) {
      flash('error', 'Passwords do not match.');
      set_old($_POST);
      redirect('/install/admin');
    }

    $config = InstallState::buildConfig($this->rootPath);
    Database::resetInstance();

    try {
      $auth = new Auth($config);
      $result = $auth->register($name, $email, $password, 'admin', true);
    } catch (\Throwable $e) {
      flash('error', 'Could not create admin account: ' . $e->getMessage());
      set_old($_POST);
      redirect('/install/admin');
    }

    if (!$result['success']) {
      flash('error', $result['error'] ?? 'Could not create admin account.');
      set_old($_POST);
      redirect('/install/admin');
    }

    InstallState::put([
      'admin_created' => true,
      'admin' => ['name' => $name, 'email' => strtolower($email)],
    ]);
    clear_old();
    Csrf::rotate();
    redirect('/install/settings');
  }

  private function postSettings(): void
  {
    $siteName = trim((string) ($_POST['site_name'] ?? 'FormFlow'));
    $timezone = (string) ($_POST['timezone'] ?? 'UTC');
    $locale = (string) ($_POST['locale'] ?? 'en');

    if ($siteName === '') {
      flash('error', 'Site name is required.');
      set_old($_POST);
      redirect('/install/settings');
    }

    if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
      $timezone = 'UTC';
    }

    InstallState::put([
      'settings' => [
        'site_name' => $siteName,
        'timezone' => $timezone,
        'locale' => $locale,
      ],
      'settings_saved' => true,
      'app_secret' => base64_encode(random_bytes(32)),
    ]);
    clear_old();
    Csrf::rotate();
    redirect('/install/complete');
  }

  private function postComplete(): void
  {
    $config = InstallState::buildConfig($this->rootPath);

    if (!ConfigWriter::write($this->rootPath, $config)) {
      flash('error', 'Could not write config.php. Check directory permissions.');
      redirect('/install/complete');
    }

    InstallState::clear();
    Csrf::rotate();
    redirect('/login');
  }

  private function apiTestDatabase(): void
  {
    $this->jsonResponse(function (): array {
      if (!Csrf::verifyRequest()) {
        return ['success' => false, 'error' => 'Invalid security token.'];
      }

      $database = $this->databaseFromPost();
      return $this->testDatabaseConfig($database);
    });
  }

  private function apiTestEmail(): void
  {
    $this->jsonResponse(function (): array {
      if (!Csrf::verifyRequest()) {
        return ['success' => false, 'error' => 'Invalid security token.'];
      }

      $smtp = $this->smtpFromPost();
      $to = trim((string) ($_POST['test_email'] ?? ''));

      if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Enter a valid test email address.'];
      }

      if (($smtp['host'] ?? '') === '') {
        return ['success' => false, 'error' => 'SMTP host is required.'];
      }

      $config = [
        'app' => ['name' => 'FormFlow'],
        'smtp' => $smtp,
      ];

      $mailer = new Mailer($config);
      $sent = $mailer->send(
        $to,
        'FormFlow — Test Email',
        '<p>This is a test email from the FormFlow installer.</p>'
      );

      return $sent
        ? ['success' => true, 'message' => 'Test email sent successfully.']
        : ['success' => false, 'error' => 'Failed to send test email. Check SMTP settings.'];
    });
  }

  /**
   * @param array{host: string, port: int, name: string, user: string, password: string, charset: string, prefix: string} $database
   * @return array{success: bool, error?: string, message?: string}
   */
  private function testDatabaseConfig(array $database): array
  {
    $config = ['database' => $database];

    $result = Database::testConnection($config);

    if (!$result['connected']) {
      return ['success' => false, 'error' => $result['error'] ?? 'Connection failed.'];
    }

    return ['success' => true, 'message' => 'Connection successful.'];
  }

  /**
   * @return array{host: string, port: int, name: string, user: string, password: string, charset: string, prefix: string}
   */
  private function databaseFromPost(): array
  {
    return [
      'host' => trim((string) ($_POST['host'] ?? '127.0.0.1')),
      'port' => (int) ($_POST['port'] ?? 3306),
      'name' => trim((string) ($_POST['name'] ?? '')),
      'user' => trim((string) ($_POST['user'] ?? '')),
      'password' => (string) ($_POST['password'] ?? ''),
      'charset' => 'utf8mb4',
      'prefix' => preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($_POST['prefix'] ?? '')) ?? '',
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function smtpFromPost(): array
  {
    $encryption = strtolower(trim((string) ($_POST['encryption'] ?? 'tls')));
    if (!in_array($encryption, ['tls', 'ssl', ''], true)) {
      $encryption = 'tls';
    }

    return [
      'host' => trim((string) ($_POST['host'] ?? '')),
      'port' => (int) ($_POST['port'] ?? 587),
      'username' => trim((string) ($_POST['username'] ?? '')),
      'password' => (string) ($_POST['password'] ?? ''),
      'encryption' => $encryption,
      'from_email' => trim((string) ($_POST['from_email'] ?? '')),
      'from_name' => trim((string) ($_POST['from_name'] ?? 'FormFlow')),
    ];
  }

  private function render(string $view, int $currentStep): void
  {
    $viewFile = $this->rootPath . '/views/install/' . $view . '.php';

    if (!is_readable($viewFile)) {
      http_response_code(500);
      echo 'Install view missing.';
      return;
    }

    $requirements = $view === 'requirements' ? RequirementsChecker::run($this->rootPath) : null;
    $state = InstallState::get();
    $steps = InstallState::STEPS;
    $pageTitle = $steps[$currentStep]['title'] ?? 'Install';
    $appName = 'FormFlow';

    ob_start();
    require $viewFile;
    $content = (string) ob_get_clean();

    require $this->rootPath . '/templates/install/layout.php';
  }

  /**
   * @param callable(): array<string, mixed> $callback
   */
  private function jsonResponse(callable $callback): void
  {
    header('Content-Type: application/json; charset=utf-8');
    try {
      echo json_encode($callback(), \JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
      http_response_code(500);
      echo json_encode(['success' => false, 'error' => 'Server error.']);
    }
  }
}
