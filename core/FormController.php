<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Form CRUD HTTP actions.
 */
class FormController
{
  /** @var array<string, mixed> */
  private array $config;

  private Auth $auth;

  private FormRepository $forms;

  /**
   * @param array<string, mixed> $config
   */
  public function __construct(array $config)
  {
    $this->config = $config;
    $this->auth = new Auth($config);
    $this->forms = new FormRepository($config);
  }

  public function store(): void
  {
    $this->requireEditor();
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token.');
      redirect('/admin/forms/new');
    }

    $user = $this->auth->user();
    if ($user === null) {
      redirect('/login');
    }

    $result = $this->forms->create((int) $user['id'], $this->parsePayload());
    if (!$result['success']) {
      flash('error', $result['error'] ?? 'Could not create form.');
      redirect('/admin/forms/new');
    }

    Csrf::rotate();
    flash('success', 'Form created successfully.');
    redirect('/admin/forms/' . $result['form_id'] . '/edit');
  }

  public function update(): void
  {
    $this->requireEditor();
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token.');
      redirect('/admin/forms');
    }

    $user = $this->auth->user();
    $formId = (int) ($_POST['form_id'] ?? 0);

    if ($user === null || $formId <= 0) {
      redirect('/admin/forms');
    }

    $result = $this->forms->update($formId, (int) $user['id'], $this->parsePayload());
    if (!$result['success']) {
      flash('error', $result['error'] ?? 'Could not update form.');
      redirect('/admin/forms/' . $formId . '/edit');
    }

    Csrf::rotate();
    flash('success', 'Form saved successfully.');
    redirect('/admin/forms/' . $formId . '/edit');
  }

  public function duplicate(): void
  {
    $this->requireEditor();
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token.');
      redirect('/admin/forms');
    }

    $user = $this->auth->user();
    $formId = (int) ($_POST['form_id'] ?? 0);

    if ($user === null || $formId <= 0) {
      redirect('/admin/forms');
    }

    $result = $this->forms->duplicate($formId, (int) $user['id']);
    if (!$result['success']) {
      flash('error', $result['error'] ?? 'Could not duplicate form.');
      redirect('/admin/forms');
    }

    Csrf::rotate();
    flash('success', 'Form duplicated.');
    redirect('/admin/forms/' . $result['form_id'] . '/edit');
  }

  public function delete(): void
  {
    $this->requireEditor();
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token.');
      redirect('/admin/forms');
    }

    $user = $this->auth->user();
    $formId = (int) ($_POST['form_id'] ?? 0);

    if ($user === null || $formId <= 0) {
      redirect('/admin/forms');
    }

    if (!$this->forms->delete($formId, (int) $user['id'])) {
      flash('error', 'Form not found.');
    } else {
      flash('success', 'Form deleted.');
    }

    Csrf::rotate();
    redirect('/admin/forms');
  }

  public function toggle(): void
  {
    $this->requireEditor();
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token.');
      redirect('/admin/forms');
    }

    $user = $this->auth->user();
    $formId = (int) ($_POST['form_id'] ?? 0);

    if ($user === null || $formId <= 0) {
      redirect('/admin/forms');
    }

    $result = $this->forms->toggleStatus($formId, (int) $user['id']);
    if (!$result['success']) {
      flash('error', $result['error'] ?? 'Could not update status.');
    } else {
      flash('success', 'Form is now ' . ($result['status'] ?? 'updated') . '.');
    }

    Csrf::rotate();
    redirect('/admin/forms');
  }

  public function useTemplate(): void
  {
    $this->requireEditor();
    if (!Csrf::verifyRequest()) {
      flash('error', 'Invalid security token.');
      redirect('/admin/templates');
    }

    $user = $this->auth->user();
    $templateId = (int) ($_POST['template_id'] ?? 0);

    if ($user === null || $templateId <= 0) {
      redirect('/admin/templates');
    }

    $templates = new TemplateRepository($this->config);
    $template = $templates->find($templateId);

    if ($template === null) {
      flash('error', 'Template not found.');
      redirect('/admin/templates');
    }

    $result = $this->forms->create((int) $user['id'], [
      'name' => (string) $template['name'],
      'fields' => $template['fields'],
      'settings' => $template['settings'],
      'status' => 'paused',
    ]);

    Csrf::rotate();

    if (!$result['success']) {
      flash('error', $result['error'] ?? 'Could not create form from template.');
      redirect('/admin/templates');
    }

    flash('success', 'Form created from template.');
    redirect('/admin/forms/' . $result['form_id'] . '/edit');
  }

  private function requireEditor(): void
  {
    if (!$this->auth->requireRole('editor')) {
      exit;
    }
  }

  /**
   * @return array<string, mixed>
   */
  private function parsePayload(): array
  {
    $fields = json_decode((string) ($_POST['fields_json'] ?? '[]'), true);
    $settings = json_decode((string) ($_POST['settings_json'] ?? '[]'), true);

    $recipients = array_filter(array_map('trim', explode(',', (string) ($_POST['notification_recipients'] ?? ''))));
    $domains = array_filter(array_map('trim', explode(',', (string) ($_POST['allowed_domains'] ?? ''))));

    if (!is_array($settings)) {
      $settings = FormDefaults::settings();
    }

    $settings['success'] = [
      'type' => ($_POST['success_type'] ?? 'message') === 'redirect' ? 'redirect' : 'message',
      'message' => trim((string) ($_POST['success_message'] ?? '')),
      'redirect_url' => trim((string) ($_POST['success_redirect_url'] ?? '')),
    ];
    $settings['notifications'] = [
      'recipients' => $recipients,
      'subject' => trim((string) ($_POST['notification_subject'] ?? 'New form submission')),
      'auto_reply' => !empty($_POST['auto_reply']),
    ];
    $settings['spam'] = [
      'honeypot' => !empty($_POST['honeypot']),
      'recaptcha' => !empty($_POST['recaptcha']),
      'recaptcha_site_key' => trim((string) ($_POST['recaptcha_site_key'] ?? '')),
      'recaptcha_secret_key' => trim((string) ($_POST['recaptcha_secret_key'] ?? '')),
    ];
    $settings['allowed_domains'] = array_values($domains);
    $settings['webhook_url'] = trim((string) ($_POST['webhook_url'] ?? ''));

    return [
      'name' => trim((string) ($_POST['name'] ?? '')),
      'slug' => trim((string) ($_POST['slug'] ?? '')),
      'status' => ($_POST['status'] ?? 'active') === 'paused' ? 'paused' : 'active',
      'fields' => is_array($fields) ? $fields : [],
      'settings' => $settings,
    ];
  }
}
