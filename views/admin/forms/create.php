<?php

declare(strict_types=1);

$form = [
    'id' => 0,
    'name' => '',
    'slug' => '',
    'status' => 'paused',
    'fields' => [],
    'settings' => \FormFlow\FormDefaults::settings(),
];
$embed = null;
$isNew = true;
require __DIR__ . '/_form.php';
