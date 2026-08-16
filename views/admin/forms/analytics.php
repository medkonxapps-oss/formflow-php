<?php

declare(strict_types=1);

use FormFlow\AnalyticsRepository;
use FormFlow\FormRepository;

$formId = (int) ($routeParams['formId'] ?? 0);
$userId = (int) ($currentUser['id'] ?? 0);
$forms = new FormRepository($config);
$form = $forms->findForUser($formId, $userId);

if ($form === null) {
    http_response_code(404);
    echo '<p class="text-zinc-600">Form not found.</p>';
    return;
}

$rawRange = $_GET['range'] ?? 'daily';
$granularity = in_array($rawRange, ['daily', 'weekly', 'monthly'], true)
    ? (string) $rawRange
    : 'daily';

$analytics = new AnalyticsRepository($config);
$overTime = $analytics->submissionsOverTime($formId, $userId, $granularity);
$referrers = $analytics->topReferrers($formId, $userId, 10);
$totals = $analytics->formTotals($formId, $userId);
$views = 0;
try {
    $views = (new \FormFlow\ViewTracker($config))->countForForm($formId, 90);
} catch (\Throwable $e) {
    $views = 0;
}
$conversion = $views > 0 ? round(($totals['total_submissions'] / $views) * 100, 1) : null;

$chartLabels = json_encode(array_column($overTime, 'period'));
$chartCounts = json_encode(array_column($overTime, 'count'));
?>

<div class="mb-6">
    <a href="/admin/forms/<?= $formId ?>/edit" class="text-sm text-zinc-500 hover:underline">&larr; Back to form</a>
    <h2 class="mt-2 text-2xl font-semibold text-zinc-900">Analytics — <?= e((string) $form['name']) ?></h2>
    <p class="mt-1 text-sm text-zinc-500"><?= (int) $totals['total_submissions'] ?> submissions · <?= (int) $totals['spam'] ?> spam filtered · <?= (int) $views ?> views<?= $conversion !== null ? ' · ' . $conversion . '% conversion' : '' ?></p>
</div>

<div class="mb-4 flex gap-2">
    <?php foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $key => $label): ?>
        <a href="?range=<?= $key ?>"
           class="rounded-md px-3 py-1.5 text-sm <?= $granularity === $key ? 'bg-zinc-900 text-white' : 'border border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50' ?>">
            <?= $label ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="mb-8 grid gap-6 lg:grid-cols-3">
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm lg:col-span-2">
        <h3 class="mb-4 text-sm font-semibold text-zinc-900">Submissions over time</h3>
        <canvas id="analyticsChart" height="120"></canvas>
    </div>
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold text-zinc-900">Top referrers</h3>
        <?php if ($referrers === []): ?>
            <p class="text-sm text-zinc-500">No referrer data yet.</p>
        <?php else: ?>
            <ul class="space-y-2 text-sm">
                <?php foreach ($referrers as $ref): ?>
                    <li class="flex justify-between gap-2">
                        <span class="truncate text-zinc-700"><?= e((string) $ref['domain']) ?></span>
                        <span class="shrink-0 font-medium text-zinc-900"><?= (int) $ref['count'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
    <h3 class="text-sm font-semibold text-zinc-900">Views vs. submissions (conversion)</h3>
    <p class="mt-2 text-sm text-zinc-600">
        <?= (int) $views ?> unique-ish views in the last 90 days
        <?php if ($conversion !== null): ?>
            → <strong><?= e((string) $conversion) ?>%</strong> converted to submissions.
        <?php else: ?>
            — open the live form once to start collecting view data.
        <?php endif; ?>
    </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(() => {
  const ctx = document.getElementById('analyticsChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= $chartLabels ?>,
      datasets: [{
        label: 'Submissions',
        data: <?= $chartCounts ?>,
        backgroundColor: '#3f3f46',
      }],
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    },
  });
})();
</script>
