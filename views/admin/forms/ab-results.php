<?php

declare(strict_types=1);

use FormFlow\AbTestRepository;

use FormFlow\FormRepository;

/** @var array<string, mixed> $config */
/** @var array<string, mixed> $routeParams */
/** @var array<string, mixed> $user */

$formId = (int) ($routeParams['formId'] ?? 0);
$userId = (int) ($currentUser['id'] ?? 0);

$forms = new FormRepository($config);
$form = $forms->findForUser($formId, $userId);

if ($form === null) {
    http_response_code(404);
    echo '<p class="text-zinc-600">Form not found.</p>';
    return;
}

$ab = new AbTestRepository($config);
$stats = [];
$winnerId = null;
try {
    $stats = $ab->getStats($formId);
    $winnerId = $ab->detectWinner($stats);
} catch (Throwable $e) {
    $stats = [];
}
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">A/B Test Results</h1>
        <p class="mt-1 text-sm text-gray-500">Form: <?= e((string)$form['name']) ?></p>
    </div>
    <a href="/admin/forms/<?= $formId ?>/edit" class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
        &larr; Back to Builder
    </a>
</div>

<?php if (empty($stats)): ?>
    <div class="rounded-lg border border-gray-200 bg-white p-12 text-center shadow-sm">
        <h3 class="text-lg font-medium text-gray-900">No A/B Test Running</h3>
        <p class="mt-2 text-sm text-gray-500">Enable A/B testing in the form builder to start collecting data.</p>
    </div>
<?php else: ?>
    <div class="grid gap-6 lg:grid-cols-2">
        <?php foreach ($stats as $s): ?>
            <?php $isWinner = ($s['id'] === $winnerId); ?>
            <div class="relative overflow-hidden rounded-xl border <?= $isWinner ? 'border-emerald-500 ring-1 ring-emerald-500' : 'border-gray-200' ?> bg-white p-6 shadow-sm">
                <?php if ($isWinner): ?>
                    <div class="absolute -right-12 top-6 rotate-45 bg-emerald-500 px-12 py-1 text-xs font-bold text-white shadow-sm">
                        WINNER
                    </div>
                <?php endif; ?>

                <div class="flex items-center gap-3 mb-4">
                    <h3 class="text-lg font-bold text-gray-900"><?= e($s['name']) ?></h3>
                    <?php if ($s['is_control']): ?>
                        <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">Control</span>
                    <?php else: ?>
                        <span class="rounded-full bg-violet-50 px-2.5 py-0.5 text-xs font-medium text-violet-700">Test</span>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Traffic</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900"><?= $s['traffic_pct'] ?>%</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Views</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900"><?= number_format($s['impressions']) ?></p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Conv. Rate</p>
                        <p class="mt-1 text-2xl font-semibold <?= $isWinner ? 'text-emerald-600' : 'text-gray-900' ?>">
                            <?= $s['conversion_rate'] ?>%
                        </p>
                        <p class="mt-0.5 text-xs text-gray-500"><?= number_format($s['conversions']) ?> subs</p>
                    </div>
                </div>

                <?php if (!$s['is_control']): ?>
                    <div class="mt-6 pt-6 border-t border-gray-100 flex justify-end">
                        <form action="/admin/forms/variants/winner" method="post" onsubmit="return confirm('This will make <?= e(addslashes($s['name'])) ?> the live form and end the A/B test. Are you sure?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form_id" value="<?= $formId ?>">
                            <input type="hidden" name="variant_id" value="<?= $s['id'] ?>">
                            <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-violet-500">
                                Declare Winner
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
