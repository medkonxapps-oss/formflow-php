<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Minimal front-controller router with middleware, actions, and dynamic segments.
 */
class Router
{
  /** @var array<string, mixed> */
  private array $config;

  /** @var array<string, array<string, array<string, mixed>>> */
  private array $routes;

  /** @var array<string, string> */
  private array $routeParams = [];

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(array $config)
  {
    $this->config = $config;
    /** @var array<string, array<string, array<string, mixed>>> $routes */
    $routes = require FORMFLOW_ROOT . '/core/routes.php';
    $this->routes = $routes;
  }

  public function dispatch(): void
  {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $path = $this->normalizePath(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

    $route = $this->matchRoute($method, $path);

    if ($route === null) {
      $this->renderError(404);
      return;
    }

    if (!$this->runMiddleware($route['middleware'] ?? [])) {
      return;
    }

    if (isset($route['action'])) {
      $this->dispatchAction((string) $route['action']);
      return;
    }

    $handler = $route['handler'] ?? null;

    if (!is_string($handler) || $handler === '') {
      $this->renderError(500, 'Invalid route handler.');
      return;
    }

    $viewFile = FORMFLOW_ROOT . '/views/' . str_replace('.', '/', $handler) . '.php';

    if (!is_readable($viewFile)) {
      $this->renderError(500, 'View not found: ' . $handler);
      return;
    }

    $layout = $route['layout'] ?? null;
    $content = $this->renderView($viewFile);

    if ($layout === null) {
      echo $content;
      return;
    }

    $layoutFile = FORMFLOW_ROOT . '/templates/' . $layout . '/layout.php';

    if (!is_readable($layoutFile)) {
      $this->renderError(500, 'Layout not found: ' . $layout);
      return;
    }

    $pageTitle = $route['title'] ?? $this->config['app']['name'] ?? 'FormFlow';
    $appName = $this->config['app']['name'] ?? 'FormFlow';
    $auth = new Auth($this->config);
    $currentUser = $auth->user();

    require $layoutFile;
  }

  /**
   * @return array<string, mixed>|null
   */
  private function matchRoute(string $method, string $path): ?array
  {
    $methodRoutes = $this->routes[$method] ?? [];

    if (isset($methodRoutes[$path])) {
      $this->routeParams = [];

      return $methodRoutes[$path];
    }

    foreach ($methodRoutes as $pattern => $route) {
      $params = $this->matchPattern($pattern, $path);
      if ($params !== null) {
        $this->routeParams = $params;

        return $route;
      }
    }

    $this->routeParams = [];

    return null;
  }

  /**
   * @return array<string, string>|null
   */
  private function matchPattern(string $pattern, string $path): ?array
  {
    if (!str_contains($pattern, '{')) {
      return null;
    }

    $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
    $regex = '#^' . $regex . '$#';

    if ($regex === null || !preg_match($regex, $path, $matches)) {
      return null;
    }

    $params = [];
    foreach ($matches as $key => $value) {
      if (is_string($key)) {
        $params[$key] = urldecode($value);
      }
    }

    return $params;
  }

  /**
   * @param list<string> $middleware
   */
  private function runMiddleware(array $middleware): bool
  {
    if ($middleware === []) {
      return true;
    }

    $auth = new Auth($this->config);

    foreach ($middleware as $entry) {
      if ($entry === 'guest') {
        if ($auth->check()) {
          redirect('/admin');
        }
        continue;
      }

      if ($entry === 'auth') {
        if (!$auth->requireAuth()) {
          return false;
        }
        continue;
      }

      if (str_starts_with($entry, 'role:')) {
        $role = substr($entry, 5);
        if (!$auth->requireRole($role)) {
          return false;
        }
      }
    }

    return true;
  }

  private function dispatchAction(string $action): void
  {
    if (!str_contains($action, '@')) {
      $this->renderError(500, 'Invalid action.');
      return;
    }

    [$class, $method] = explode('@', $action, 2);

    if ($class === 'AuthController') {
      $controller = new AuthController($this->config);
      if (!method_exists($controller, $method)) {
        $this->renderError(500, 'Action not found.');
        return;
      }
      $controller->{$method}();
      return;
    }

    if ($class === 'FormController') {
      $controller = new FormController($this->config);
      if (!method_exists($controller, $method)) {
        $this->renderError(500, 'Action not found.');
        return;
      }
      $controller->{$method}();
      return;
    }

    if ($class === 'SubmissionController') {
      $controller = new SubmissionController($this->config, $this->routeParams);
      if (!method_exists($controller, $method)) {
        $this->renderError(500, 'Action not found.');
        return;
      }
      $controller->{$method}();
      return;
    }

    if ($class === 'SubmitController') {
      $controller = new SubmitController($this->config, $this->routeParams);
      if (!method_exists($controller, $method)) {
        $this->renderError(500, 'Action not found.');
        return;
      }
      $controller->{$method}();
      return;
    }

    if ($class === 'SubmissionFileController') {
      $controller = new SubmissionFileController($this->config, $this->routeParams);
      if (!method_exists($controller, $method)) {
        $this->renderError(500, 'Action not found.');
        return;
      }
      $controller->{$method}();
      return;
    }

    if ($class === 'SettingsController') {
      $controller = new SettingsController($this->config);
      if (!method_exists($controller, $method)) {
        $this->renderError(500, 'Action not found.');
        return;
      }
      $controller->{$method}();
      return;
    }

    $this->renderError(500, 'Unknown controller.');
  }

  private function normalizePath(string $path): string
  {
    $path = '/' . trim($path, '/');

    return $path === '/' ? '/' : rtrim($path, '/');
  }

  private function renderView(string $viewFile): string
  {
    ob_start();
    /** @var array<string, mixed> $config */
    $config = $this->config;
    $routeParams = $this->routeParams;
    $auth = new Auth($this->config);
    $currentUser = $auth->user();
    require $viewFile;

    return (string) ob_get_clean();
  }

  private function renderError(int $code, ?string $message = null): void
  {
    http_response_code($code);

    $viewFile = FORMFLOW_ROOT . '/views/errors/' . $code . '.php';

    if (!is_readable($viewFile)) {
      header('Content-Type: text/plain; charset=utf-8');
      echo $code === 404 ? 'Not Found' : 'Internal Server Error';
      if (FORMFLOW_DEBUG && $message !== null) {
        echo "\n" . $message;
      }
      return;
    }

    ob_start();
    /** @var array<string, mixed> $config */
    $config = $this->config;
    $errorMessage = $message;
    require $viewFile;
    $content = (string) ob_get_clean();

    $layoutFile = FORMFLOW_ROOT . '/templates/public/layout.php';
    $pageTitle = (string) $code . ' — ' . ($config['app']['name'] ?? 'FormFlow');
    $appName = $config['app']['name'] ?? 'FormFlow';

    require $layoutFile;
  }
}
