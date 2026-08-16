<?php

declare(strict_types=1);

use FormFlow\AbTestRepository;
use FormFlow\EmbedGenerator;
use FormFlow\FormRepository;

$slug = (string) ($routeParams['slug'] ?? '');
$repo = new FormRepository($config);
$form = $repo->findPublicBySlugOrId($slug);

if (!headers_sent()) {
    header('Content-Security-Policy: frame-ancestors *');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Private-Network: true');
}

if ($form === null || ($form['status'] ?? '') !== 'active') {
    http_response_code(404);
    echo '<p class="text-center text-gray-600">Form not found or not published.</p>';
    return;
}

// ── A/B Test: assign variant (sticky via cookie) ─────────────────────────
$abEnabled  = !empty(($form['settings']['ab_test']['enabled'] ?? false));
$abVariant  = null;
$abToken    = '';
$forceId    = (int) ($_GET['force_variant'] ?? 0);

try {
    $abRepo = new AbTestRepository($config);
    if ($forceId > 0) {
        $abVariant = $abRepo->findVariant((int) $form['id'], $forceId);
    } elseif ($abEnabled) {
        $abToken = (string) ($_COOKIE['ff_ab_session'] ?? '');
        if ($abToken === '' || strlen($abToken) < 16) {
            $abToken = bin2hex(random_bytes(16));
            setcookie('ff_ab_session', $abToken, [
                'expires'  => time() + 86400 * 30,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        $abVariant = $abRepo->assignVariant((int) $form['id'], $abToken);
    }

    if ($abVariant !== null && (int) $abVariant['is_control'] !== 1) {
        $variantFields = json_decode((string) ($abVariant['fields_json'] ?? ''), true);
        if (is_string($variantFields)) {
            $variantFields = json_decode($variantFields, true);
        }
        if (is_array($variantFields) && $variantFields !== []) {
            $form['fields'] = $variantFields;
        }
        if (!empty($abVariant['settings_json'])) {
            $variantSettings = json_decode((string) $abVariant['settings_json'], true);
            if (is_array($variantSettings)) {
                $form['settings'] = array_merge(
                    is_array($form['settings'] ?? null) ? $form['settings'] : [],
                    $variantSettings
                );
            }
        }
    }
} catch (\Throwable $e) {
    $abVariant = null;
}

$embed    = EmbedGenerator::generate($form, $config);
$formHtml = $embed['inline_html'] ?? $embed['html'];
$formName = (string) ($form['name'] ?? 'Form');
$abLabel  = is_array($abVariant) ? (string) ($abVariant['name'] ?? '') : '';
?>

<div class="mx-auto max-w-3xl px-4 py-6">
    <?php if ($abLabel !== ''): ?>
        <p class="mb-3 text-center text-xs text-gray-500">Showing variant: <strong><?= e($abLabel) ?></strong></p>
    <?php endif; ?>
    <div class="formflow-preview">
        <?= $formHtml ?>
    </div>
</div>

<style>
.formflow-preview .ff-form { box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
.formflow-preview .ff-field { margin-bottom: 1rem; }
.formflow-preview label { display: block; font-size: 0.875rem; }
.formflow-preview input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]),
.formflow-preview textarea,
.formflow-preview select {
    margin-top: 0.25rem; width: 100%; border: 1px solid #d1d5db;
    padding: 0.5rem 0.75rem; font-size: 0.875rem; box-sizing: border-box;
}
.formflow-preview textarea { min-height: 5rem; }
.formflow-preview button[type="submit"]:hover { opacity: 0.9; }
.formflow-preview fieldset { border: none; padding: 0; margin: 0; }
.formflow-preview legend { margin-bottom: 0.25rem; }
</style>


