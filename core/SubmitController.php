<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Public submission endpoint controller.
 */
class SubmitController
{
  /** @var array<string, mixed> */
  private array $config;

  /** @var array<string, string> */
  private array $routeParams;

  /**
   * @param array<string, mixed> $config
   * @param array<string, string> $routeParams
   */
  public function __construct(array $config, array $routeParams = [])
  {
    $this->config = $config;
    $this->routeParams = $routeParams;
  }

  public function options(): void
  {
    $slug = (string) ($this->routeParams['slug'] ?? '');
    $forms = new FormRepository($this->config);
    $form = $forms->findPublicBySlugOrId($slug);

    if ($form === null) {
      http_response_code(404);
      exit;
    }

    if (!CorsHandler::apply($form, $this->config)) {
      http_response_code(403);
      exit;
    }

    http_response_code(204);
    exit;
  }

  public function submit(): void
  {
    $slug = (string) ($this->routeParams['slug'] ?? '');
    $service = new SubmissionService($this->config);
    $service->handle($slug);
  }
}
