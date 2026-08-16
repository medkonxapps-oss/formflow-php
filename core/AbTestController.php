<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * HTTP actions for A/B testing management.
 */
class AbTestController
{
    private Auth $auth;
    private AbTestRepository $ab;
    private FormRepository $forms;

    /** @param array<string, mixed> $config */
    public function __construct(private array $config)
    {
        $this->auth  = new Auth($config);
        $this->ab    = new AbTestRepository($config);
        $this->forms = new FormRepository($config);
    }

    /**
     * POST /admin/forms/variants/save
     * Save variants for a form.
     */
    public function save(): void
    {
        $this->requireEditor();
        if (!Csrf::verifyRequest()) {
            $this->jsonError('Invalid CSRF token.', 403);
        }

        $user   = $this->auth->user();
        $formId = (int) ($_POST['form_id'] ?? 0);

        if ($user === null || $formId <= 0) {
            $this->jsonError('Invalid request.', 400);
        }

        // Check ownership
        if ($this->forms->findForUser($formId, (int) $user['id']) === null) {
            $this->jsonError('Form not found.', 404);
        }

        $variantsRaw = json_decode((string) ($_POST['variants_json'] ?? '[]'), true);
        if (!is_array($variantsRaw)) {
            $this->jsonError('Invalid variants data.', 400);
        }

        try {
            $this->ab->saveVariants($formId, $variantsRaw);
            $this->ab->enableForForm($formId);
        } catch (\Throwable $e) {
            $this->jsonError(FORMFLOW_DEBUG ? $e->getMessage() : 'Could not save variants. Run database migrations.', 500);
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * POST /admin/forms/variants/winner
     * Declare a winning variant and merge it back into the main form.
     */
    public function declareWinner(): void
    {
        $this->requireEditor();
        if (!Csrf::verifyRequest()) {
            flash('error', 'Invalid CSRF token.');
            redirect('/admin/forms');
        }

        $user      = $this->auth->user();
        $formId    = (int) ($_POST['form_id']    ?? 0);
        $variantId = (int) ($_POST['variant_id'] ?? 0);

        if ($user === null || $formId <= 0 || $variantId <= 0) {
            flash('error', 'Invalid request.');
            redirect('/admin/forms');
        }

        $ok = $this->ab->declareWinner($variantId, $formId, (int) $user['id']);
        Csrf::rotate();

        if ($ok) {
            flash('success', 'Winner declared! Winning variant is now the live form.');
        } else {
            flash('error', 'Could not declare winner.');
        }

        redirect('/admin/forms/' . $formId . '/edit');
    }

    // ─── helpers ──────────────────────────────────────────────────────────

    private function requireEditor(): void
    {
        if (!$this->auth->requireRole('editor')) {
            exit;
        }
    }

    /** @return never */
    private function jsonError(string $msg, int $code = 400): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }
}
