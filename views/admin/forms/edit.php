<?php

declare(strict_types=1);

use FormFlow\EmbedGenerator;
use FormFlow\FormRepository;

$userId = (int) ($currentUser['id'] ?? 0);
$formId = (int) ($routeParams['id'] ?? 0);
$repo = new FormRepository($config);
$form = $repo->findForUser($formId, $userId);

if ($form === null) {
    http_response_code(404);
    echo '<p>Form not found.</p>';
    return;
}

$embed = EmbedGenerator::generate($form, $config);
$isNew = false;
require __DIR__ . '/_form.php';
